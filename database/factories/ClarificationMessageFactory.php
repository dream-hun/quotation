<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClarificationMessage;
use App\Models\ClarificationThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClarificationMessage> */
final class ClarificationMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'thread_id' => ClarificationThread::factory(),
            'author_id' => User::factory(),
            'body' => 'Please confirm the delivery entrance.',
        ];
    }
}
