<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Currency> */
final class CurrencyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->word(),
            'minor_digits' => 2,
            'active' => true,
        ];
    }
}
