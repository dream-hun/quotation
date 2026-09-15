<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EvaluationCandidate;
use App\Models\LineAssessment;
use App\Models\QuotationLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LineAssessment> */
final class LineAssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'candidate_id' => EvaluationCandidate::factory(),
            'quotation_line_id' => fn (array $attributes): QuotationLine => QuotationLine::query()->where('revision_id', EvaluationCandidate::query()->where('id', $attributes['candidate_id'])->firstOrFail()->quotation_revision_id)->firstOrFail(),
            'result' => 'pending',
            'evidence_note' => 'Awaiting specification review',
            'assessed_by' => User::factory(),
            'assessed_at' => now(),
        ];
    }
}
