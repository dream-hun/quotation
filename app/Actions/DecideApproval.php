<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

final readonly class DecideApproval
{
    public function __construct(private RecordProcurementEvent $audit, private NotifyProcurementUsers $notify) {}

    public function handle(User $actor, PurchaseRequest $request, int $version, string $decision, string $reason): void
    {
        abort_unless(ProcurementAccess::role($actor, 'approver'), 403);
        DB::transaction(function () use ($actor, $request, $version, $decision, $reason): void {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            ProcurementGuard::require($request->status === 'pending_approval', 'This request has no pending approval.');
            $round = $request->evaluationRounds()->where('status', 'frozen')->orderByDesc('round_number')->firstOrFail();
            $case = $round->approvalCase()->lockForUpdate()->firstOrFail();
            abort_unless($case->assigned_approver_id === $actor->id && $request->assigned_approver_id === $actor->id, 403);
            ProcurementGuard::approver($request, $round->prepared_by);
            ProcurementGuard::version($case, $version);
            ProcurementGuard::require($case->status === 'pending', 'This approval was already decided.');
            ProcurementGuard::require(in_array($decision, ['approve', 'return', 'reject'], true), 'Choose an approval decision.');
            ProcurementGuard::require($decision === 'approve' || filled($reason), 'A return or rejection requires a reason.', 'reason');
            $round->load(['recommendedCandidate.quotationRevision', 'candidates.lineAssessments', 'candidates.criterionAssessments']);
            $revision = $round->recommendedCandidate?->quotationRevision;
            if ($decision === 'approve') {
                ProcurementGuard::require($revision !== null && $revision->valid_until !== null
                    && $revision->valid_until->toDateString() >= now()->timezone($request->timezone)->toDateString(),
                    'The recommended quotation expired. Return for reevaluation or reject.');
            }

            $case->update(['status' => ['approve' => 'approved', 'return' => 'returned', 'reject' => 'rejected'][$decision],
                'decided_by' => $actor->id, 'decided_at' => now(), 'decision_reason' => $reason ?: null, 'lock_version' => $case->lock_version + 1]);
            if ($decision === 'approve') {
                $request->award()->create(['approval_case_id' => $case->id, 'quotation_revision_id' => $revision->id,
                    'approved_total' => $revision->total_amount, 'currency_code' => $revision->currency_code,
                    'awarded_by' => $actor->id, 'awarded_at' => now()]);
                $request->status = 'awarded';
            } elseif ($decision === 'return') {
                $next = $request->evaluationRounds()->create(['round_number' => $round->round_number + 1, 'prepared_by' => $round->prepared_by, 'status' => 'draft']);
                foreach ($round->candidates as $candidate) {
                    $copy = $next->candidates()->create(['quotation_revision_id' => $candidate->quotation_revision_id,
                        'disposition' => 'pending', 'disposition_reason' => null]);
                    foreach (['lineAssessments', 'criterionAssessments'] as $relation) {
                        foreach ($candidate->$relation as $assessment) {
                            $finding = $assessment->replicate(['uuid']);
                            $finding->candidate_id = $copy->id;
                            $finding->save();
                        }
                    }
                }

                $request->status = 'evaluating';
            } else {
                $request->status = 'rejected';
            }

            $request->lock_version++;
            $request->save();
            $this->audit->handle($actor, $request, 'approval.'.$decision, $case, ['reason' => $reason]);
            $this->notify->handle($request, [$request->created_by, $round->prepared_by], 'approval.'.$decision, 'decision:'.$case->uuid);
            if ($decision !== 'return') {
                $request->load(['invitations.quotation', 'invitations.supplier.members' => fn (Relation $query) => $query->where('status', 'active')->select(['id', 'supplier_id', 'user_id'])]);
                foreach ($request->invitations as $invitation) {
                    $selected = $decision === 'approve' && $invitation->quotation?->id === $revision->quotation_id;
                    $recipients = $invitation->supplier->members->pluck('user_id')->all();
                    $this->notify->handle($request, $recipients, $selected ? 'outcome.selected' : 'outcome.not_selected', 'outcome:'.$case->uuid);
                }
            }
        });
    }
}
