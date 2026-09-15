<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuditEvent> */
final class AuditEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'actor_label' => 'Procurement buyer',
            'action' => 'request.created',
            'target_type' => 'purchase_request',
            'target_id' => (string) Str::uuid7(),
            'occurred_at' => now(),
            'correlation_id' => (string) Str::uuid7(),
            'safe_details' => [],
        ];
    }
}
