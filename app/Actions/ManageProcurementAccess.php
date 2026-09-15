<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/** @phpstan-type Input array{type: string, uuid?: ?string, lock_version: int, name?: string, email?: string, status?: string, roles?: list<string>, supplier_uuid?: ?string, replacement_uuid?: ?string, reason?: ?string, legal_name?: string, contact_email?: string, timezone?: string, default_currency_code?: string, code?: string, minor_digits?: int, active?: bool} */
final readonly class ManageProcurementAccess
{
    public function __construct(private RecordProcurementEvent $audit) {}

    /** @param Input $data */
    public function handle(User $actor, array $data): bool
    {
        abort_unless(ProcurementAccess::role($actor, 'administrator'), 403);
        $newUser = null;
        DB::transaction(function () use ($actor, $data, &$newUser): void {
            $type = $data['type'];
            if ($type === 'supplier') {
                $record = empty($data['uuid']) ? new Supplier : Supplier::query()->where('uuid', $data['uuid'])->lockForUpdate()->firstOrFail();
                if ($record->exists) {
                    ProcurementGuard::version($record, (int) $data['lock_version']);
                }

                $record->fill(['legal_name' => $data['legal_name'] ?? '', 'contact_email' => $data['contact_email'] ?? '', 'status' => $data['status'] ?? 'active']);
            } elseif ($type === 'organization') {
                $record = Organization::query()->lockForUpdate()->first() ?? new Organization;
                if ($record->exists) {
                    ProcurementGuard::version($record, (int) $data['lock_version']);
                }

                $record->fill(['name' => $data['name'] ?? '', 'timezone' => $data['timezone'] ?? 'UTC', 'default_currency_code' => $data['default_currency_code'] ?? '']);
            } elseif ($type === 'currency') {
                $record = Currency::query()->where('code', ($data['code'] ?? ''))->lockForUpdate()->first() ?? new Currency(['code' => ($data['code'] ?? '')]);
                if ($record->exists) {
                    ProcurementGuard::version($record, (int) $data['lock_version']);
                }

                $record->fill(['name' => $data['name'] ?? '', 'minor_digits' => $data['minor_digits'] ?? 2, 'active' => $data['active'] ?? true]);
            } else {
                $record = empty($data['uuid']) ? new User(['password' => Str::random(64)]) : User::query()->where('uuid', $data['uuid'])->lockForUpdate()->firstOrFail();
                if ($record->exists) {
                    ProcurementGuard::version($record, (int) $data['lock_version']);
                }

                $roles = $data['roles'] ?? [];
                ProcurementGuard::require(empty($data['supplier_uuid']) || $roles === [], 'Supplier representatives cannot hold internal roles.');
                ProcurementGuard::require($record->id !== $actor->id || (($data['status'] ?? 'active') === 'active' && in_array('administrator', $roles, true)), 'Keep your own administrator account active.');
                if ($record->exists && (($data['status'] ?? 'active') !== 'active' || ! in_array('approver', $roles, true))) {
                    $assigned = PurchaseRequest::query()->where('assigned_approver_id', $record->id)->whereNotIn('status', ['awarded', 'rejected', 'cancelled'])->lockForUpdate()->get();
                    foreach ($assigned as $request) {
                        $replacement = User::query()->where('uuid', $data['replacement_uuid'] ?? '')->first();
                        ProcurementGuard::require($replacement !== null && ProcurementAccess::role($replacement, 'approver') && filled($data['reason'] ?? null), 'Provide an independent replacement approver and a reason.');
                        $request->assigned_approver_id = $replacement->id;
                        $round = $request->evaluationRounds()->orderByDesc('round_number')->first();
                        ProcurementGuard::approver($request, $round?->prepared_by);
                        $request->lock_version++;
                        $request->save();
                        if ($round !== null) {
                            $round->approvalCase()->where('status', 'pending')->update(['assigned_approver_id' => $replacement->id, 'lock_version' => DB::raw('lock_version + 1')]);
                        }

                        $this->audit->handle($actor, $request, 'approver.reassigned', $request, ['reason' => $data['reason']]);
                    }
                }

                $record->fill(['name' => $data['name'] ?? '', 'email' => $data['email'] ?? '', 'status' => $data['status'] ?? 'active']);
                $isNew = ! $record->exists;
                $record->lock_version = ($record->lock_version ?? 0) + 1;
                $record->save();
                $record->roles()->delete();
                $record->supplierMembership()->delete();
                if (! empty($data['supplier_uuid'])) {
                    $supplier = Supplier::query()->where('uuid', $data['supplier_uuid'])->where('status', 'active')->firstOrFail();
                    $record->supplierMembership()->create(['supplier_id' => $supplier->id, 'status' => 'active', 'assigned_by' => $actor->id]);
                } else {
                    foreach ($roles as $role) {
                        $record->roles()->create(['role_code' => $role, 'assigned_by' => $actor->id]);
                    }
                }

                if ($isNew) {
                    $newUser = $record;
                }

                $this->audit->handle($actor, null, 'access.updated', $record, ['roles' => $roles, 'supplier_uuid' => $data['supplier_uuid'] ?? null, 'status' => $record->status]);

                return;
            }

            $record->lock_version = ($record->lock_version ?? 0) + 1;
            $record->save();

            $this->audit->handle($actor, null, 'configuration.updated', $record);
        });
        if ($newUser !== null) {
            try {
                return Password::sendResetLink(['email' => $newUser->email]) === Password::RESET_LINK_SENT;
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }
}
