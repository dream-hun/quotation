<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class WithdrawQuotation
{
    public function __construct(private RecordProcurementEvent $audit) {}

    public function handle(User $actor, PurchaseRequest $request, string $operation, string $reason, int $version): void
    {
        DB::transaction(function () use ($actor, $request, $operation, $reason, $version): void {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            $supplierId = ProcurementAccess::supplier($actor, $request);
            ProcurementGuard::open($request);
            $invitation = $request->invitations()->where('supplier_id', $supplierId)->firstOrFail();
            $quotation = $invitation->quotation()->lockForUpdate()->first();
            if ($operation === 'withdraw') {
                ProcurementGuard::require($quotation !== null && $quotation->revisions()->where('status', 'submitted')->exists(), 'No submitted quotation to withdraw.');
                ProcurementGuard::version($quotation, $version);
                $quotation->update(['withdrawn_at' => now(), 'withdrawn_by' => $actor->id, 'withdrawal_reason' => $reason, 'lock_version' => $quotation->lock_version + 1]);
            } else {
                ProcurementGuard::require(in_array($operation, ['decline', 'participate'], true), 'Unknown participation action.');
                ProcurementGuard::require($quotation === null || ! $quotation->revisions()->where('status', 'submitted')->exists(), 'Withdraw a submitted quotation instead of declining.');
                ProcurementGuard::version($invitation, $version);
                $invitation->update(['declined_at' => $operation === 'decline' ? now() : null,
                    'decline_reason' => $operation === 'decline' ? $reason : null, 'lock_version' => $invitation->lock_version + 1]);
            }

            $this->audit->handle($actor, $request, 'quotation.'.$operation, $invitation, ['reason' => $reason]);
        });
    }
}
