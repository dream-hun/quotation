<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\EvaluationRound;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class StartEvaluation
{
    public function __construct(private RecordProcurementEvent $audit) {}

    public function handle(User $actor, PurchaseRequest $request, int $version): EvaluationRound
    {
        ProcurementAccess::buyer($actor);

        return DB::transaction(function () use ($actor, $request, $version): EvaluationRound {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            ProcurementGuard::version($request, $version);
            ProcurementGuard::require(in_array($request->status, ['open', 'closed'], true)
                && $request->deadline_at !== null && ProcurementGuard::clock()->greaterThanOrEqualTo($request->deadline_at),
                'Evaluation can begin only after the deadline.');
            ProcurementGuard::approver($request, $actor->id);
            $round = $request->evaluationRounds()->create([
                'round_number' => ($request->evaluationRounds()->count()) + 1,
                'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            $request->load('invitations.quotation.latestSubmittedRevision');
            foreach ($request->invitations as $invitation) {
                $quotation = $invitation->quotation;
                $revision = $quotation?->latestSubmittedRevision;
                if ($revision === null) {
                    continue;
                }

                $reason = $quotation->withdrawn_at !== null ? 'Quotation withdrawn.' : null;
                if ($revision->valid_until === null || $revision->valid_until->toDateString() < now()->timezone($request->timezone)->toDateString()) {
                    $reason = 'Quotation expired.';
                }

                $round->candidates()->create([
                    'quotation_revision_id' => $revision->id, 'disposition' => $reason === null ? 'pending' : 'excluded',
                    'disposition_reason' => $reason,
                ]);
            }

            $request->update(['status' => 'evaluating', 'closed_at' => $request->deadline_at, 'lock_version' => $request->lock_version + 1]);
            $this->audit->handle($actor, $request, 'evaluation.started', $round);

            return $round;
        });
    }
}
