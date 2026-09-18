<?php

namespace App\Http\Controllers;

use App\Models\GlossaryChangeHistory;
use App\Models\GlossaryEntity;
use App\Models\GlossaryEntityRedirect;
use App\Models\GlossaryEvidence;
use App\Models\GlossaryFact;
use App\Models\GlossaryProposal;
use App\Models\Novel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlossaryManagerController extends Controller
{
    public function proposals(Request $request, Novel $novel): JsonResponse
    {
        $proposals = GlossaryProposal::with(['observation.chapter.volume', 'entity', 'fact'])
            ->where('novel_id', $novel->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('operation'), fn ($query) => $query->where('operation', $request->string('operation')))
            ->latest()
            ->paginate(min($request->integer('per_page', 50), 100));

        return response()->json($proposals);
    }

    public function decideProposal(Request $request, Novel $novel, GlossaryProposal $proposal): JsonResponse
    {
        if ($proposal->novel_id !== $novel->id || $proposal->status !== 'pending') {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($proposal, $data, $request): void {
            $proposal->load(['fact', 'entity', 'observation']);
            if ($data['action'] === 'approve' && $proposal->operation === 'update_fact' && $proposal->fact) {
                $before = $proposal->fact->only(['value', 'previous_value', 'status', 'authority']);
                $proposal->fact->update([
                    'previous_value' => $proposal->fact->value,
                    'value' => data_get($proposal->after_data, 'value'),
                    'status' => 'active',
                    'authority' => 'admin',
                    'last_updated_at' => now(),
                ]);
                $this->recordHistory($proposal, $request->user()->id, 'proposal_approved', 'fact', $proposal->fact->id, $before, $proposal->fact->only(['value', 'previous_value', 'status', 'authority']), $data['notes'] ?? null);
            }
            $proposal->update(['status' => $data['action'] === 'approve' ? 'approved' : 'rejected', 'reason' => $data['notes'] ?? $proposal->reason]);
        });

        return response()->json(['proposal' => $proposal->fresh(['entity', 'fact', 'observation'])]);
    }

    public function updateFact(Request $request, Novel $novel, GlossaryFact $fact): JsonResponse
    {
        if ($fact->novel_id !== $novel->id) {
            return response()->json(['message' => 'Fact not found'], 404);
        }

        $data = $request->validate([
            'fact_key' => 'sometimes|string|max:100',
            'value' => 'required|string|max:10000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $before = $fact->only(['fact_key', 'value', 'previous_value', 'status', 'authority']);
        $fact->update([
            'fact_key' => $data['fact_key'] ?? $fact->fact_key,
            'previous_value' => $fact->value,
            'value' => $data['value'],
            'status' => 'active',
            'authority' => 'admin',
            'last_updated_at' => now(),
        ]);
        $this->recordHistory(null, $request->user()->id, 'admin_edit', 'fact', $fact->id, $before, $fact->only(['fact_key', 'value', 'previous_value', 'status', 'authority']), $data['notes'] ?? null, $novel->id);

        return response()->json(['fact' => $fact->fresh(['entity', 'evidence'])]);
    }

    public function updateEntity(Request $request, Novel $novel, GlossaryEntity $entity): JsonResponse
    {
        if ($entity->novel_id !== $novel->id) {
            return response()->json(['message' => 'Entity not found'], 404);
        }

        $data = $request->validate([
            'canonical_name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:40',
            'aliases' => 'sometimes|array',
            'aliases.*' => 'string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $before = $entity->only(['canonical_name', 'type', 'aliases', 'status', 'authority']);
        $entity->update([
            'canonical_name' => $data['canonical_name'] ?? $entity->canonical_name,
            'normalized_name' => $this->normalize($data['canonical_name'] ?? $entity->canonical_name),
            'type' => $data['type'] ?? $entity->type,
            'aliases' => $data['aliases'] ?? $entity->aliases,
            'authority' => 'admin',
        ]);
        $this->recordHistory(null, $request->user()->id, 'admin_edit', 'entity', $entity->id, $before, $entity->only(['canonical_name', 'type', 'aliases', 'status', 'authority']), $data['notes'] ?? null, $novel->id);

        return response()->json(['entity' => $entity->fresh()]);
    }

    public function mergeEntities(Request $request, Novel $novel): JsonResponse
    {
        $data = $request->validate([
            'source_entity_id' => 'required|integer',
            'target_entity_id' => 'required|integer|different:source_entity_id',
            'notes' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($data, $novel, $request): void {
            $source = GlossaryEntity::where('novel_id', $novel->id)->findOrFail($data['source_entity_id']);
            $target = GlossaryEntity::where('novel_id', $novel->id)->findOrFail($data['target_entity_id']);
            foreach ($source->facts()->with('evidence')->get() as $sourceFact) {
                $targetFact = $target->facts()->where('fact_key', $sourceFact->fact_key)->first();
                if (!$targetFact) {
                    $sourceFact->update(['entity_id' => $target->id]);
                    continue;
                }

                foreach ($sourceFact->evidence as $evidence) {
                    $targetFact->evidence()->firstOrCreate(
                        ['chapter_id' => $evidence->chapter_id, 'quote' => $evidence->quote],
                        ['context' => $evidence->context, 'confidence' => $evidence->confidence],
                    );
                }
                $sourceFact->delete();
            }
            GlossaryEntityRedirect::create([
                'novel_id' => $novel->id,
                'source_entity_id' => $source->id,
                'target_entity_id' => $target->id,
                'created_by' => $request->user()->id,
                'reason' => $data['notes'] ?? 'Admin entity merge',
            ]);
            $before = ['source_id' => $source->id, 'target_id' => $target->id, 'source_status' => $source->status];
            $source->update(['status' => 'superseded', 'authority' => 'admin']);
            $this->recordHistory(null, $request->user()->id, 'entity_merge', 'entity', $source->id, $before, ['target_id' => $target->id, 'source_status' => 'superseded'], $data['notes'] ?? null, $novel->id);
        });

        return response()->json(['message' => 'Entities merged']);
    }

    public function history(Request $request, Novel $novel): JsonResponse
    {
        return response()->json(
            GlossaryChangeHistory::where('novel_id', $novel->id)->latest()->paginate(min($request->integer('per_page', 50), 100))
        );
    }

    public function updateEvidence(Request $request, Novel $novel, GlossaryEvidence $evidence): JsonResponse
    {
        $evidence->load('fact');
        if (!$evidence->fact || $evidence->fact->novel_id !== $novel->id) {
            return response()->json(['message' => 'Evidence not found'], 404);
        }

        $data = $request->validate([
            'quote' => 'sometimes|string|max:20000',
            'context' => 'nullable|string|max:20000',
            'notes' => 'nullable|string|max:2000',
        ]);
        $chapter = $evidence->chapter()->first();
        if (($data['quote'] ?? $evidence->quote) && !str_contains($chapter->content, $data['quote'] ?? $evidence->quote)) {
            return response()->json(['message' => 'Evidence quote must exist in the source chapter'], 422);
        }

        $before = $evidence->only(['quote', 'context', 'confidence']);
        $evidence->update([
            'quote' => $data['quote'] ?? $evidence->quote,
            'context' => $data['context'] ?? $evidence->context,
        ]);
        $this->recordHistory(null, $request->user()->id, 'admin_edit', 'evidence', $evidence->id, $before, $evidence->only(['quote', 'context', 'confidence']), $data['notes'] ?? null, $novel->id);

        return response()->json(['evidence' => $evidence->fresh(['chapter', 'fact'])]);
    }

    public function rollback(Request $request, Novel $novel, GlossaryChangeHistory $history): JsonResponse
    {
        if ($history->novel_id !== $novel->id) {
            return response()->json(['message' => 'History entry not found'], 404);
        }

        DB::transaction(function () use ($history, $request, $novel): void {
            $subject = match ($history->subject_type) {
                'fact' => GlossaryFact::where('novel_id', $novel->id)->findOrFail($history->subject_id),
                'entity' => GlossaryEntity::where('novel_id', $novel->id)->findOrFail($history->subject_id),
                'evidence' => GlossaryEvidence::whereHas('fact', fn ($query) => $query->where('novel_id', $novel->id))->findOrFail($history->subject_id),
                default => throw new \RuntimeException('This history entry cannot be rolled back.'),
            };
            $before = $subject->toArray();
            $subject->fill($history->before_data ?? [])->save();
            $this->recordHistory(null, $request->user()->id, 'rollback', $history->subject_type, $subject->id, $before, $subject->toArray(), 'Rollback of history entry '.$history->id, $novel->id);
        });

        return response()->json(['message' => 'Glossary change rolled back']);
    }

    private function recordHistory(
        ?GlossaryProposal $proposal,
        int $actorId,
        string $changeType,
        string $subjectType,
        int $subjectId,
        array $before,
        array $after,
        ?string $reason,
        ?int $novelId = null,
    ): void {
        GlossaryChangeHistory::create([
            'novel_id' => $novelId ?? $proposal?->novel_id,
            'proposal_id' => $proposal?->id,
            'actor_id' => $actorId,
            'actor_type' => 'admin',
            'change_type' => $changeType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_data' => $before,
            'after_data' => $after,
            'reason' => $reason,
        ]);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }
}
