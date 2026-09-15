<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\EvaluationCandidate;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** @phpstan-type Input array{lock_version: int, candidate_uuid?: string, reason?: ?string, lines: list<array{uuid: string, result: string, finding: string}>, criteria: list<array{uuid: string, result: string, finding: string}>} */
final readonly class AssessCandidate
{
    public function __construct(private RecordProcurementEvent $audit) {}

    /** @param Input $data */
    public function handle(User $actor, PurchaseRequest $request, string $candidateUuid, array $data): void
    {
        ProcurementAccess::buyer($actor);
        DB::transaction(function () use ($actor, $request, $candidateUuid, $data): void {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            $candidate = EvaluationCandidate::query()->where('uuid', $candidateUuid)->whereHas('round',
                fn ($query) => $query->where('request_id', $request->id))->with(['round', 'quotationRevision.lines', 'quotationRevision.quotation'])->firstOrFail();
            ProcurementGuard::version($candidate, (int) $data['lock_version']);
            abort_unless($candidate->round->prepared_by === $actor->id, 403);
            ProcurementGuard::require($request->status === 'evaluating' && $candidate->round->status === 'draft', 'This evaluation is frozen.');
            $lines = $candidate->quotationRevision->lines->keyBy('uuid');
            $criteria = $request->criteria()->get()->keyBy('uuid');
            $candidate->lineAssessments()->delete();
            $candidate->criterionAssessments()->delete();
            $eligible = count($data['lines']) === $lines->count() && count($data['criteria']) === $criteria->count();
            $pending = ! $eligible;
            foreach ($data['lines'] as $entry) {
                $line = $lines->get($entry['uuid']);
                ProcurementGuard::require($line !== null, 'Assessment line belongs to another quotation.');
                $candidate->lineAssessments()->create([
                    'quotation_line_id' => $line->id, 'result' => $entry['result'], 'evidence_note' => $entry['finding'],
                    'assessed_by' => $actor->id, 'assessed_at' => now(),
                ]);
                $eligible = $eligible && $entry['result'] === 'pass';
                $pending = $pending || $entry['result'] === 'pending';
            }

            foreach ($data['criteria'] as $entry) {
                $criterion = $criteria->get($entry['uuid']);
                ProcurementGuard::require($criterion !== null, 'Criterion belongs to another request.');
                $candidate->criterionAssessments()->create([
                    'criterion_id' => $criterion->id, 'result' => $entry['result'], 'finding' => $entry['finding'],
                    'assessed_by' => $actor->id, 'assessed_at' => now(),
                ]);
                $eligible = $eligible && $entry['result'] !== 'pending' && (! $criterion->mandatory || $entry['result'] === 'pass');
                $pending = $pending || $entry['result'] === 'pending';
            }

            $revision = $candidate->quotationRevision;
            $invalid = $revision->quotation->withdrawn_at !== null || $revision->valid_until === null
                || $revision->valid_until->toDateString() < now()->timezone($request->timezone)->toDateString();
            $disposition = $invalid ? 'excluded' : ($eligible ? 'eligible' : ($pending ? 'pending' : 'excluded'));
            $reason = $invalid ? 'Quotation withdrawn or expired.' : ($data['reason'] ?? null);
            ProcurementGuard::require($disposition !== 'excluded' || filled($reason), 'Explain why this candidate is excluded.', 'reason');
            $candidate->update(['disposition' => $disposition, 'disposition_reason' => $reason,
                'assessed_at' => now(), 'lock_version' => $candidate->lock_version + 1]);
            $candidate->round->increment('lock_version');

            $this->audit->handle($actor, $request, 'evaluation.assessed', $candidate, ['disposition' => $disposition]);
        });
    }
}
