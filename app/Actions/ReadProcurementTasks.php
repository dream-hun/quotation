<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Enums\ProcurementPermission;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReadProcurementTasks
{
    /** @return array<string, mixed> */
    public function handle(User $actor): array
    {
        $visible = ProcurementAccess::requests($actor);
        $now = ProcurementGuard::clock();
        $columns = ['uuid', 'request_number', 'title', 'status', 'deadline_at', 'timezone'];
        $deadlines = (clone $visible)->where('status', 'open')->where('deadline_at', '>', $now)
            ->where('deadline_at', '<=', $now->addDays(7))->orderBy('deadline_at')->orderBy('id');
        $buyer = ProcurementAccess::can($actor, ProcurementPermission::ManageRequests);
        $approver = ProcurementAccess::can($actor, ProcurementPermission::DecideApprovals);
        $supplierId = ProcurementAccess::supplierId($actor);
        $tasks = (clone $visible)->where(function (Builder $query) use ($actor, $buyer, $approver, $supplierId, $now): void {
            $query->whereRaw('1 = 0');
            if ($buyer) {
                $query->orWhere(fn (Builder $query) => $query->where('created_by', $actor->id)->where('status', 'draft'))
                    ->orWhere(fn (Builder $query) => $query->where('created_by', $actor->id)->whereIn('status', ['open', 'closed'])->where('deadline_at', '<=', $now))
                    ->orWhere(fn (Builder $query) => $query->where('status', 'evaluating')->whereHas('evaluationRounds',
                        fn (Builder $query) => $query->where('status', 'draft')->where('prepared_by', $actor->id)));
            }

            if ($approver) {
                $query->orWhere(fn (Builder $query) => $query->where('status', 'pending_approval')
                    ->where('assigned_approver_id', $actor->id)->where('created_by', '!=', $actor->id)
                    ->whereHas('evaluationRounds', fn (Builder $query) => $query->where('status', 'frozen')->where('prepared_by', '!=', $actor->id)
                        ->whereHas('approvalCase', fn (Builder $query) => $query->where('status', 'pending')->where('assigned_approver_id', $actor->id))));
            }

            if ($supplierId !== null) {
                $query->orWhere(fn (Builder $query) => $query->where('status', 'open')->where('deadline_at', '>', $now)
                    ->whereHas('invitations', fn (Builder $query) => $query->where('supplier_id', $supplierId)->whereNull('declined_at')
                        ->whereDoesntHave('quotation', fn (Builder $query) => $query->whereNull('withdrawn_at')->has('latestSubmittedRevision'))));
            }
        })->orderBy('created_at')->orderBy('id');

        return [
            'approachingDeadlines' => ['total' => (clone $deadlines)->count(), 'data' => $deadlines->limit(8)->get($columns)],
            'assignedTasks' => ['total' => (clone $tasks)->count(), 'data' => $tasks->limit(8)->get($columns)->map(fn (PurchaseRequest $request): array => $request->only($columns) + [
                'action' => match ($request->status) {
                    'draft' => 'Complete request',
                    'pending_approval' => 'Review recommendation',
                    'evaluating' => 'Continue evaluation',
                    default => $supplierId !== null ? 'Prepare quotation' : 'Start evaluation',
                },
            ])],
        ];
    }
}
