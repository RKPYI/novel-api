<?php

namespace App\Jobs;

use App\Models\GlossaryChangeHistory;
use App\Models\GlossaryEvidence;
use App\Models\GlossaryFact;
use App\Models\GlossaryProposal;
use App\Services\GlossaryAdjudicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdjudicateGlossaryProposal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public readonly int $proposalId)
    {
    }

    public function handle(GlossaryAdjudicationService $service): void
    {
        $proposal = GlossaryProposal::findOrFail($this->proposalId);
        if ($proposal->status !== 'pending') {
            return;
        }

        $assessment = $service->assess($proposal);
        $classificationReason = match ($assessment['classification']) {
            'true_conflict' => 'A later observation is likely a direct contradiction and needs review.',
            'chapter_scoped_correction' => 'A later observation looks like a chapter-scoped correction or progressive reveal rather than a direct contradiction.',
            'new_fact_slot' => 'The evidence appears to describe a different fact slot rather than the same fact.',
            default => 'The observed change does not look like a direct glossary conflict.',
        };

        $proposal->update([
            'ai_recommendation' => $assessment['recommendation'],
            'ai_explanation' => $assessment['explanation'],
            'classification' => $assessment['classification'],
            'reason' => $classificationReason,
        ]);

        if (in_array($assessment['classification'], ['chapter_scoped_correction', 'new_fact_slot'], true)) {
            $this->autoApply($proposal, $assessment['classification']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        GlossaryProposal::whereKey($this->proposalId)->update([
            'ai_recommendation' => 'needs_review',
            'ai_explanation' => $exception?->getMessage() ?? 'Adjudication failed.',
        ]);
    }

    private function autoApply(GlossaryProposal $proposal, string $classification): void
    {
        DB::transaction(function () use ($proposal, $classification): void {
            $proposal->load(['fact', 'entity', 'observation']);
            $newValue = (string) data_get($proposal->after_data, 'value', '');

            if ($classification === 'chapter_scoped_correction' && $proposal->fact) {
                $before = $proposal->fact->only(['value', 'previous_value', 'status', 'authority']);
                $proposal->fact->update([
                    'previous_value' => $proposal->fact->value,
                    'value' => $newValue,
                    'status' => 'active',
                    'authority' => 'ai',
                    'last_updated_at' => now(),
                ]);

                GlossaryChangeHistory::create([
                    'novel_id' => $proposal->novel_id,
                    'proposal_id' => $proposal->id,
                    'actor_id' => null,
                    'actor_type' => 'ai',
                    'change_type' => 'auto_approved_correction',
                    'subject_type' => 'fact',
                    'subject_id' => $proposal->fact->id,
                    'before_data' => $before,
                    'after_data' => $proposal->fact->fresh()->only(['value', 'previous_value', 'status', 'authority']),
                    'reason' => 'Auto-approved as a chapter-scoped correction.',
                ]);
            }

            if ($classification === 'new_fact_slot' && $proposal->entity) {
                $baseKey = (string) data_get($proposal->after_data, 'fact_key', $proposal->fact?->fact_key ?? 'fact');
                $newKey = $this->uniqueFactKey($proposal->entity->id, $baseKey);
                $newFact = GlossaryFact::create([
                    'novel_id' => $proposal->novel_id,
                    'entity_id' => $proposal->entity->id,
                    'fact_key' => $newKey,
                    'value' => $newValue,
                    'confidence' => $proposal->confidence,
                    'status' => 'active',
                    'authority' => 'ai',
                    'first_seen_chapter_id' => $proposal->observation?->chapter_id,
                    'last_seen_chapter_id' => $proposal->observation?->chapter_id,
                    'last_updated_at' => now(),
                ]);

                if ($proposal->observation) {
                    GlossaryEvidence::firstOrCreate(
                        [
                            'fact_id' => $newFact->id,
                            'chapter_id' => $proposal->observation->chapter_id,
                            'quote' => $proposal->observation->quote,
                        ],
                        [
                            'context' => $proposal->observation->context,
                            'confidence' => $proposal->confidence,
                        ]
                    );
                }

                GlossaryChangeHistory::create([
                    'novel_id' => $proposal->novel_id,
                    'proposal_id' => $proposal->id,
                    'actor_id' => null,
                    'actor_type' => 'ai',
                    'change_type' => 'auto_new_fact_slot',
                    'subject_type' => 'fact',
                    'subject_id' => $newFact->id,
                    'before_data' => ['value' => null],
                    'after_data' => $newFact->only(['fact_key', 'value', 'status', 'authority']),
                    'reason' => 'Auto-created a new fact slot because the evidence appears to be a separate fact.',
                ]);
            }

            $proposal->update(['status' => 'approved']);
        });
    }

    private function uniqueFactKey(int $entityId, string $baseKey): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($baseKey)) ?: 'fact';
        $candidate = $base;
        $attempt = 2;

        while (GlossaryFact::where('entity_id', $entityId)->where('fact_key', $candidate)->exists()) {
            $candidate = $base.'_'.$attempt;
            $attempt++;
        }

        return $candidate;
    }
}
