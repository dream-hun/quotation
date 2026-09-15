<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
final class SupplierFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'contact_email' => fake()->safeEmail(),
            'status' => 'active',
        ];
    }
}
