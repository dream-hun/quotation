<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProcurementNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProcurementNotification> */
final class ProcurementNotificationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'event_key' => (string) Str::uuid7(),
            'event_type' => 'invitation.created',
            'safe_payload' => ['message' => 'A procurement event requires your attention.'],
            'email_status' => 'not_required',
            'email_attempts' => 0,
        ];
    }
}
