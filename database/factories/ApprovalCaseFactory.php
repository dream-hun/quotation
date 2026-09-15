<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalCase;
use App\Models\EvaluationRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalCase> */
final class ApprovalCaseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'round_id' => EvaluationRound::factory()->frozen(),
            'assigned_approver_id' => User::factory(),
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->afterCreating(function (ApprovalCase $case): void {
            $case->update([
                'status' => 'approved',
                'decided_by' => $case->assigned_approver_id,
                'decided_at' => now(),
            ]);
        });
    }
}
