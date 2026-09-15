<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Concerns\QuotationMoney;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** @phpstan-type Input array{lock_version: int, candidate_uuid: string, reason: string, participation_justification?: ?string} */
final readonly class FreezeEvaluation
{
    public function __construct(private RecordProcurementEvent $audit, private NotifyProcurementUsers $notify) {}

    /** @param Input $data */
    public function handle(User $actor, PurchaseRequest $request, array $data): void
    {
        ProcurementAccess::buyer($actor);
        DB::transaction(function () use ($actor, $request, $data): void {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            $round = $request->evaluationRounds()->where('status', 'draft')->lockForUpdate()->firstOrFail();
            ProcurementGuard::version($round, (int) $data['lock_version']);
            ProcurementGuard::require($request->status === 'evaluating', 'This request is not being evaluated.');
            ProcurementGuard::require($round->prepared_by === $actor->id, 'Only the round preparer may freeze this recommendation.');
            ProcurementGuard::approver($request, $round->prepared_by);
            ProcurementGuard::require(! $request->clarificationThreads()->where('material', true)->where('status', 'open')->exists(), 'Resolve all material clarifications first.');
            $round->load(['candidates.quotationRevision.lines.requestItem', 'candidates.quotationRevision.quotation', 'candidates.lineAssessments', 'candidates.criterionAssessments']);
            $criteria = $request->criteria()->get();
            $candidates = $round->candidates;
            ProcurementGuard::require($candidates->isNotEmpty() && ! $candidates->contains('disposition', 'pending'), 'Give every submitted candidate a disposition.');
            foreach ($candidates->where('disposition', 'eligible') as $candidate) {
                $revision = $candidate->quotationRevision;
                $criterionAssessments = $candidate->criterionAssessments->keyBy('criterion_id');
                ProcurementGuard::require($revision->quotation->withdrawn_at === null && $revision->valid_until !== null
                    && $revision->valid_until->toDateString() >= now()->timezone($request->timezone)->toDateString(), 'An eligible quotation is withdrawn or expired.');
                ProcurementGuard::require($candidate->lineAssessments->count() === $revision->lines->count()
                    && $candidate->lineAssessments->every(fn ($entry): bool => $entry->result === 'pass')
                    && $candidate->criterionAssessments->count() === $criteria->count()
                    && $criteria->every(function ($criterion) use ($criterionAssessments): bool {
                        $finding = $criterionAssessments->get($criterion->id);

                        return $finding !== null && filled($finding->finding) && $finding->result !== 'pending'
                            && (! $criterion->mandatory || $finding->result === 'pass');
                    }), 'Complete all line and criterion assessments.');
            }

            $recommended = $candidates->firstWhere('uuid', $data['candidate_uuid']);
            ProcurementGuard::require($recommended !== null && $recommended->disposition === 'eligible', 'Recommend an eligible quotation.', 'candidate_uuid');
            ProcurementGuard::require($candidates->where('disposition', 'eligible')->count() !== 1 || filled($data['participation_justification'] ?? null), 'Explain why proceeding with one eligible offer is justified.', 'participation_justification');
            $request->load('items');
            $snapshot = [
                'request' => $request->only(['uuid', 'request_number', 'title', 'description', 'delivery_destination', 'currency_code', 'currency_precision', 'timezone', 'deadline_at']),
                'items' => $request->items->toArray(), 'criteria' => $criteria->toArray(),
                'candidates' => $candidates->toArray(), 'generated_at' => now()->toISOString(),
            ];
            $round->update([
                'recommended_candidate_id' => $recommended->id, 'recommendation_reason' => $data['reason'],
                'participation_justification' => $data['participation_justification'] ?? null,
                'frozen_snapshot' => $snapshot, 'calculation_version' => QuotationMoney::VERSION,
                'submitted_at' => now(), 'status' => 'frozen', 'lock_version' => $round->lock_version + 1,
            ]);
            $case = $round->approvalCase()->create(['assigned_approver_id' => $request->assigned_approver_id, 'status' => 'pending', 'requested_at' => now()]);
            $request->update(['status' => 'pending_approval', 'lock_version' => $request->lock_version + 1]);
            $this->audit->handle($actor, $request, 'evaluation.frozen', $round);
            $this->notify->handle($request, [$case->assigned_approver_id], 'approval.requested', 'approval:'.$case->uuid);
        });
    }
}
