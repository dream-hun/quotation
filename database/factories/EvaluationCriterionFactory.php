<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationCriterion> */
final class EvaluationCriterionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => PurchaseRequest::factory(),
            'name' => 'Specification compliance',
            'category' => 'specification',
            'mandatory' => true,
            'assessment_rule' => 'Meets every required specification',
            'display_order' => 1,
        ];
    }
}
