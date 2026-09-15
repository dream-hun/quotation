<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationRound;
use App\Models\Invitation;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationCandidate> */
final class EvaluationCandidateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'round_id' => EvaluationRound::factory(),
            'quotation_revision_id' => fn (array $attributes): QuotationRevision => QuotationRevision::factory()->submitted()->create(['quotation_id' => Quotation::factory()->create(['invitation_id' => Invitation::factory()->create(['request_id' => EvaluationRound::query()->where('id', $attributes['round_id'])->firstOrFail()->request_id])->id])->id]),
            'disposition' => 'pending',
        ];
    }
}
