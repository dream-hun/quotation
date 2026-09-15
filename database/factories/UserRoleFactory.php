<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserRole> */
final class UserRoleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_code' => 'buyer',
            'assigned_by' => User::factory(),
        ];
    }
}
