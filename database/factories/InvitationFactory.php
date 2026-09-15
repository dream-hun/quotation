<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invitation> */
final class InvitationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => PurchaseRequest::factory(),
            'supplier_id' => Supplier::factory(),
            'invited_by' => User::factory(),
            'invited_at' => now(),
        ];
    }
}
