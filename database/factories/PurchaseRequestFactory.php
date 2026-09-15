<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseRequest> */
final class PurchaseRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => fn (): Organization => Organization::query()->first() ?? Organization::factory()->create(),
            'request_number' => 'RFQ-'.Str::uuid7(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'delivery_destination' => fake()->address(),
            'currency_code' => fn (array $attributes): string => Organization::query()->where('id', $attributes['organization_id'])->firstOrFail()->default_currency_code,
            'currency_precision' => fn (array $attributes): int => Currency::query()->where('code', $attributes['currency_code'])->firstOrFail()->minor_digits,
            'deadline_at' => now()->addWeek(),
            'timezone' => 'Africa/Kigali',
            'status' => 'draft',
            'created_by' => User::factory(),
            'assigned_approver_id' => User::factory(),
        ];
    }
}
