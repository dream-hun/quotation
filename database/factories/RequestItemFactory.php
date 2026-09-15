<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestItem> */
final class RequestItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => PurchaseRequest::factory(),
            'line_number' => 1,
            'description' => 'Office chair',
            'specification' => 'Adjustable height and back support',
            'quantity' => '10.0000',
            'unit' => 'each',
        ];
    }
}
