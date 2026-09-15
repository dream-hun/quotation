<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\PurchaseRequest;
use App\Models\SupplierMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ChangePurchaseRequest
{
    public function __construct(private RecordProcurementEvent $audit, private NotifyProcurementUsers $notify) {}

    public function handle(User $actor, PurchaseRequest $request, int $version, string $operation, string $reason, ?string $deadline = null): PurchaseRequest
    {
        ProcurementAccess::buyer($actor);

        return DB::transaction(function () use ($actor, $request, $version, $operation, $reason, $deadline): PurchaseRequest {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            ProcurementGuard::version($request, $version);
            ProcurementGuard::require(filled($reason), 'Provide a reason.', 'reason');
            if ($operation === 'extend') {
                ProcurementGuard::open($request);
                ProcurementGuard::require($deadline !== null && $request->deadline_at !== null && CarbonImmutable::parse($deadline)->greaterThan($request->deadline_at), 'Choose a later deadline.', 'deadline_at');
                $request->deadline_at = CarbonImmutable::parse($deadline)->utc();
            } elseif ($operation === 'cancel') {
                ProcurementGuard::require(in_array($request->status, ['draft', 'open', 'closed', 'evaluating'], true), 'This request cannot be cancelled.');
                $request->status = 'cancelled';
                $request->cancellation_reason = $reason;
            } elseif ($operation === 'clone') {
                $copy = $request->replicate(['uuid', 'published_at', 'closed_at', 'cancellation_reason']);
                $copy->request_number = 'RFQ-'.Str::upper((string) Str::ulid());
                $copy->cloned_from_id = $request->id;
                $copy->created_by = $actor->id;
                $copy->assigned_approver_id = null;
                $copy->status = 'draft';
                $copy->deadline_at = null;
                $copy->lock_version = 1;
                $copy->save();
                $request->load(['items', 'criteria']);
                foreach (['items', 'criteria'] as $relation) {
                    foreach ($request->$relation as $record) {
                        $child = $record->replicate(['uuid']);
                        $child->request_id = $copy->id;
                        $child->save();
                    }
                }

                $this->audit->handle($actor, $copy, 'request.cloned', $copy, ['source' => $request->uuid, 'reason' => $reason]);

                return $copy;
            } else {
                throw ValidationException::withMessages(['workflow' => 'Unknown operation.']);
            }

            $request->lock_version++;
            $request->save();
            $recipients = SupplierMember::query()->whereIn('supplier_id', $request->invitations()->select('supplier_id'))->where('status', 'active')->pluck('user_id')->all();
            $this->notify->handle($request, $recipients, 'request.'.$operation, $request->uuid.':'.$request->lock_version);
            $this->audit->handle($actor, $request, 'request.'.$operation, $request, ['reason' => $reason]);

            return $request;
        });
    }
}
