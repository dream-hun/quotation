<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Models\Attachment;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\ProcurementNotification;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class ReadAdministration
{
    /** @return array<string, mixed> */
    public function handle(User $actor, string $search = ''): array
    {
        abort_unless(ProcurementAccess::role($actor, 'administrator'), 403);

        return [
            'users' => User::query()->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                ->with(['roles', 'supplierMembership.supplier'])->orderBy('id')->paginate(20)->through(fn ($user): array => $user->only(['uuid', 'name', 'email', 'status', 'lock_version']) + [
                    'roles' => $user->roles->pluck('role_code'), 'supplier_uuid' => $user->supplierMembership?->supplier->uuid,
                ]),
            'suppliers' => Supplier::query()->orderBy('legal_name')->limit(100)->get(['uuid', 'legal_name', 'contact_email', 'status', 'lock_version']),
            'organization' => Organization::query()->first()?->only(['uuid', 'name', 'timezone', 'default_currency_code', 'lock_version']),
            'currencies' => Currency::query()->orderBy('code')->get(['uuid', 'code', 'name', 'minor_digits', 'active', 'lock_version']),
            'pendingScans' => Attachment::query()->where('scan_status', 'pending')->count(),
            'scannerConfigured' => filled(config('procurement.scanner_binary')),
            'workerHealthy' => Cache::has('procurement.worker_heartbeat'),
            'emailFailures' => ProcurementNotification::query()->where('email_status', 'failed')->count(),
            'q' => $search,
        ];
    }
}
