<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Enums\ProcurementPermission;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\ProcurementNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class ReadAdministration
{
    /** @return array<string, mixed> */
    public function handle(User $actor): array
    {
        abort_unless(ProcurementAccess::can($actor, ProcurementPermission::ManageAccess), 403);

        return [
            'organization' => Organization::query()->first()?->only(['uuid', 'name', 'timezone', 'default_currency_code', 'lock_version']),
            'currencies' => Currency::query()->orderBy('code')->get(['uuid', 'code', 'name', 'minor_digits', 'active', 'lock_version']),
            'workerHealthy' => Cache::has('procurement.worker_heartbeat'),
            'emailFailures' => ProcurementNotification::query()->where('email_status', 'failed')->count(),
        ];
    }
}
