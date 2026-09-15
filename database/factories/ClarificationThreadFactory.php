<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClarificationThread;
use App\Models\Invitation;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClarificationThread> */
final class ClarificationThreadFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => PurchaseRequest::factory(),
            'supplier_id' => fn (array $attributes): int => Invitation::factory()->create(['request_id' => $attributes['request_id']])->supplier_id,
            'visibility' => 'private',
            'subject' => 'Clarify delivery access',
            'status' => 'open',
            'material' => false,
            'opened_by' => User::factory(),
        ];
    }
}
