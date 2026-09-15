<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $request_id
 * @property int $approval_case_id
 * @property int $quotation_revision_id
 * @property string $approved_total
 * @property string $currency_code
 * @property int $awarded_by
 * @property CarbonImmutable $awarded_at
 * @property CarbonImmutable|null $created_at
 * @property-read PurchaseRequest $request
 * @property-read ApprovalCase $approvalCase
 * @property-read QuotationRevision $quotationRevision
 * @property-read Currency $currency
 * @property-read User $awardedBy
 */
final class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    /** @return BelongsTo<ApprovalCase, $this> */
    public function approvalCase(): BelongsTo
    {
        return $this->belongsTo(ApprovalCase::class, 'approval_case_id');
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function quotationRevision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approved_total' => 'decimal:4',
            'awarded_at' => 'immutable_datetime',
        ];
    }
}
