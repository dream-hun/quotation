<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationRound;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationRound> */
final class EvaluationRoundFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => PurchaseRequest::factory(),
            'round_number' => 1,
            'status' => 'draft',
            'prepared_by' => User::factory(),
        ];
    }

    public function frozen(): static
    {
        return $this->afterCreating(function (EvaluationRound $round): void {
            $candidate = EvaluationCandidate::factory()->create(['round_id' => $round->id, 'disposition' => 'eligible']);

            $round->update([
                'status' => 'frozen',
                'recommended_candidate_id' => $candidate->id,
                'recommendation_reason' => 'Meets the requirements with acceptable commercial terms.',
                'participation_justification' => 'Single eligible supplier in this test fixture.',
                'frozen_snapshot' => ['candidate_uuid' => $candidate->uuid],
                'calculation_version' => 'v1',
                'submitted_at' => now(),
            ]);
        });
    }
}
