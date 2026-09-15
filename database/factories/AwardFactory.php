<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalCase;
use App\Models\Award;
use App\Models\QuotationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<Award> */
final class AwardFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'approval_case_id' => ApprovalCase::factory()->approved(),
            'request_id' => fn (array $attributes): int => ApprovalCase::query()->where('id', $attributes['approval_case_id'])->firstOrFail()->round->request_id,
            'quotation_revision_id' => fn (array $attributes): int => ApprovalCase::query()->where('id', $attributes['approval_case_id'])->firstOrFail()->round->recommendedCandidate()->firstOrFail()->quotation_revision_id,
            'approved_total' => fn (array $attributes): string => QuotationRevision::query()->where('id', $attributes['quotation_revision_id'])->firstOrFail()->total_amount ?? throw new LogicException('An award requires a submitted total.'),
            'currency_code' => fn (array $attributes): string => QuotationRevision::query()->where('id', $attributes['quotation_revision_id'])->firstOrFail()->currency_code ?? throw new LogicException('An award requires a submitted currency.'),
            'awarded_by' => fn (array $attributes): int => ApprovalCase::query()->where('id', $attributes['approval_case_id'])->firstOrFail()->decided_by ?? throw new LogicException('An award requires a decided approval.'),
            'awarded_at' => now(),
        ];
    }
}
