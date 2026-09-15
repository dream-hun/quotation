<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierMember> */
final class SupplierMemberFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'supplier_id' => Supplier::factory(),
            'status' => 'active',
            'assigned_by' => User::factory(),
        ];
    }
}
