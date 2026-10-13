<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProcurementRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Description('Bootstrap the first administrator without default passwords')]
#[Signature('procurement:setup {email : Existing active account email to make the first administrator}')]
final class SetupProcurement extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');

        return DB::transaction(function () use ($email): int {
            if (User::query()->role(ProcurementRole::Administrator)->exists()) {
                $this->error('An administrator already exists. Use Administration to manage access.');

                return self::FAILURE;
            }

            $user = User::query()->where('normalized_email', mb_strtolower($email))->lockForUpdate()->first();
            if ($user === null || $user->status !== 'active' || $user->supplier_id !== null) {
                $this->error('Register an active internal account first.');

                return self::FAILURE;
            }

            $user->assignRole(ProcurementRole::Administrator);
            AuditEvent::query()->create([
                'actor_user_id' => $user->id, 'actor_label' => 'Installation setup', 'action' => 'administrator.bootstrapped',
                'target_type' => 'User', 'target_id' => $user->uuid, 'occurred_at' => now(),
                'correlation_id' => (string) Str::uuid(), 'safe_details' => [],
            ]);
            $this->info('Administrator assigned. Sign in and configure currencies, organization, suppliers and accounts.');

            return self::SUCCESS;
        });
    }
}
