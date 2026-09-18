<?php

namespace App\Jobs;

use App\Models\GlossaryProposal;
use App\Services\GlossaryAdjudicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        $proposal->update([
            'ai_recommendation' => $assessment['recommendation'],
            'ai_explanation' => $assessment['explanation'],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        GlossaryProposal::whereKey($this->proposalId)->update([
            'ai_recommendation' => 'needs_review',
            'ai_explanation' => $exception?->getMessage() ?? 'Adjudication failed.',
        ]);
    }
}
