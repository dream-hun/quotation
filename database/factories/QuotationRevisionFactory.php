<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<QuotationRevision> */
final class QuotationRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'revision_number' => 1,
            'status' => 'draft',
            'prepared_by' => User::factory(),
        ];
    }

    public function submitted(): static
    {
        return $this->afterCreating(function (QuotationRevision $revision): void {
            $request = $revision->quotation->invitation->request;
            $item = $request->items()->first() ?? RequestItem::factory()->create(['request_id' => $request->id]);
            QuotationLine::factory()->create(['revision_id' => $revision->id, 'request_item_id' => $item->id]);

            $revision->update([
                'status' => 'submitted',
                'supplier_name_snapshot' => $revision->quotation->invitation->supplier->legal_name,
                'currency_code' => $request->currency_code,
                'valid_until' => now()->addMonth()->toDateString(),
                'delivery_days' => 7,
                'payment_terms' => 'Payment within 30 days of delivery',
                'delivery_charge' => '10000.0000',
                'total_amount' => '299100.0000',
                'calculation_version' => 'v1',
                'submitted_by' => $revision->prepared_by,
                'submitted_at' => now(),
                'submission_key' => (string) Str::uuid7(),
            ]);
        });
    }
}
