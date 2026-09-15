<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
final class QuotationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
        ];
    }
}
