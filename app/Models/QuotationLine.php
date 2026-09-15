<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\QuotationLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $revision_id
 * @property int $request_item_id
 * @property string|null $offered_description
 * @property string|null $unit_price
 * @property string|null $discount_amount
 * @property string|null $tax_amount
 * @property string|null $line_net
 * @property string|null $line_total
 * @property string|null $supplier_compliance_response
 * @property string|null $zero_price_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read QuotationRevision $revision
 * @property-read RequestItem $requestItem
 */
final class QuotationLine extends Model
{
    /** @use HasFactory<QuotationLineFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<QuotationRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'revision_id');
    }

    /** @return BelongsTo<RequestItem, $this> */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class, 'request_item_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_net' => 'decimal:4',
            'line_total' => 'decimal:4',
            'lock_version' => 'integer',
        ];
    }
}
