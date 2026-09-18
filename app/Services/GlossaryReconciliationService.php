<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\GlossaryChangeHistory;
use App\Models\GlossaryEntity;
use App\Models\GlossaryEvidence;
use App\Models\GlossaryFact;
use App\Models\GlossaryExtractionRun;
use App\Models\GlossaryObservation;
use App\Models\GlossaryProposal;
use App\Jobs\AdjudicateGlossaryProposal;
use Illuminate\Support\Facades\DB;

class GlossaryReconciliationService
{
    public function reconcile(Chapter $chapter, GlossaryExtractionRun $run, array $payload): void
    {
        DB::transaction(function () use ($chapter, $run, $payload): void {
            foreach ($payload['entries'] as $entry) {
                $normalizedName = $this->normalize($entry['name']);
                $entity = GlossaryEntity::query()
                    ->where('novel_id', $chapter->novel_id)
                    ->where('type', $entry['type'])
                    ->where(function ($query) use ($normalizedName): void {
                        $query->where('normalized_name', $normalizedName)
                            ->orWhereJsonContains('aliases', $normalizedName);
                    })
                    ->first();

                if (!$entity) {
                    $entity = GlossaryEntity::create([
                        'novel_id' => $chapter->novel_id,
                        'type' => $entry['type'],
                        'canonical_name' => $entry['name'],
                        'normalized_name' => $normalizedName,
                        'aliases' => $entry['aliases'],
                        'status' => 'active',
                        'authority' => 'ai',
                    ]);
                } elseif ($entity->authority !== 'admin') {
                    $entity->update([
                        'aliases' => array_values(array_unique(array_merge($entity->aliases ?? [], $entry['aliases']))),
                    ]);
                }

                foreach ($entry['facts'] as $factData) {
                    if (!str_contains($chapter->content, $factData['quote'])) {
                        continue;
                    }

                    $observation = GlossaryObservation::firstOrCreate(
                        [
                            'extraction_run_id' => $run->id,
                            'entity_type' => $entry['type'],
                            'normalized_name' => $normalizedName,
                            'fact_key' => $factData['key'],
                            'quote' => $factData['quote'],
                        ],
                        [
                            'novel_id' => $chapter->novel_id,
                            'chapter_id' => $chapter->id,
                            'entity_name' => $entry['name'],
                            'value' => $factData['value'],
                            'context' => $factData['context'],
                            'confidence' => $factData['confidence'],
                            'payload' => $factData,
                        ],
                    );

                    $fact = GlossaryFact::firstOrNew([
                        'entity_id' => $entity->id,
                        'fact_key' => $factData['key'],
                    ]);
                    $isNew = !$fact->exists;
                    $oldValue = $fact->value;

                    if ($isNew) {
                        $fact->fill([
                            'novel_id' => $chapter->novel_id,
                            'value' => $factData['value'],
                            'confidence' => $factData['confidence'],
                            'status' => $this->statusFor($factData['confidence']),
                            'authority' => 'ai',
                            'first_seen_chapter_id' => $chapter->id,
                        ]);
                        $fact->last_seen_chapter_id = $chapter->id;
                        $fact->last_updated_at = now();
                        $fact->save();
                    } elseif ($oldValue === $factData['value']) {
                        $fact->fill([
                            'confidence' => max((float) $fact->confidence, (float) $factData['confidence']),
                            'last_seen_chapter_id' => $chapter->id,
                            'last_updated_at' => now(),
                        ])->save();
                    } elseif ($fact->authority === 'admin' || $fact->status === 'active') {
                        $this->proposal($chapter, $observation, $entity, $fact, $factData, $oldValue);
                    } else {
                        $fact->previous_value = $oldValue;
                        $fact->value = $factData['value'];
                        $fact->confidence = $factData['confidence'];
                        $fact->status = $this->statusFor($factData['confidence']);
                        $fact->last_seen_chapter_id = $chapter->id;
                        $fact->last_updated_at = now();
                        $fact->save();
                    }

                    GlossaryEvidence::firstOrCreate(
                        ['fact_id' => $fact->id, 'chapter_id' => $chapter->id, 'quote' => $factData['quote']],
                        ['context' => $factData['context'], 'confidence' => $factData['confidence']],
                    );
                }
            }
        });
    }

    private function proposal(
        Chapter $chapter,
        GlossaryObservation $observation,
        GlossaryEntity $entity,
        GlossaryFact $fact,
        array $factData,
        string $oldValue,
    ): void {
        $proposal = GlossaryProposal::firstOrCreate(
            [
                'observation_id' => $observation->id,
                'operation' => 'update_fact',
                'status' => 'pending',
            ],
            [
                'novel_id' => $chapter->novel_id,
                'entity_id' => $entity->id,
                'fact_id' => $fact->id,
                'confidence' => $factData['confidence'],
                'before_data' => ['value' => $oldValue],
                'after_data' => ['value' => $factData['value'], 'fact_key' => $factData['key']],
                'reason' => 'A later observation conflicts with the current canonical fact.',
            ],
        );
        if ($proposal->wasRecentlyCreated) {
            AdjudicateGlossaryProposal::dispatch($proposal->id)->afterCommit();
        }
    }

    private function statusFor(float $confidence): string
    {
        return $confidence >= config('services.openrouter.high_confidence_threshold') ? 'active' : 'pending_review';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }
}
