<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationLine> */
final class QuotationLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'revision_id' => QuotationRevision::factory(),
            'request_item_id' => fn (array $attributes): RequestItem => RequestItem::factory()->create(['request_id' => QuotationRevision::query()->where('id', $attributes['revision_id'])->firstOrFail()->quotation->invitation->request_id]),
            'unit_price' => '25000.0000',
            'discount_amount' => '5000.0000',
            'tax_amount' => '44100.0000',
            'line_net' => '245000.0000',
            'line_total' => '289100.0000',
            'supplier_compliance_response' => 'Meets the requested specification',
        ];
    }
}
