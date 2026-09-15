<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CriterionAssessment;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCriterion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CriterionAssessment> */
final class CriterionAssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'candidate_id' => EvaluationCandidate::factory(),
            'criterion_id' => fn (array $attributes): EvaluationCriterion => EvaluationCriterion::factory()->create(['request_id' => EvaluationCandidate::query()->where('id', $attributes['candidate_id'])->firstOrFail()->round->request_id]),
            'result' => 'pending',
            'finding' => 'Awaiting criterion review',
            'assessed_by' => User::factory(),
            'assessed_at' => now(),
        ];
    }
}
