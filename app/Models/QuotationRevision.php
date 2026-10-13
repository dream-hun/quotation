<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\QuotationRevisionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property-read int|null $draft_quotation_id
 * @property int $quotation_id
 * @property int $revision_number
 * @property string $status
 * @property string|null $supplier_reference
 * @property string|null $supplier_name_snapshot
 * @property string|null $supplier_registration_snapshot
 * @property string|null $currency_code
 * @property CarbonImmutable|null $valid_until
 * @property int|null $delivery_days
 * @property string|null $payment_terms
 * @property string|null $advance_percent
 * @property int|null $balance_due_days
 * @property string|null $delivery_charge
 * @property string|null $total_amount
 * @property string|null $calculation_version
 * @property int $prepared_by
 * @property int|null $submitted_by
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $submission_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read Quotation $quotation
 * @property-read Currency|null $currency
 * @property-read User $preparedBy
 * @property-read User|null $submittedBy
 * @property-read Collection<int, QuotationLine> $lines
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, EvaluationCandidate> $evaluationCandidates
 * @property-read Collection<int, ClarificationThread> $clarificationThreads
 */
final class QuotationRevision extends Model
{
    /** @use HasFactory<QuotationRevisionFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasMany<QuotationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class, 'revision_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'revision_id');
    }

    /** @return HasMany<EvaluationCandidate, $this> */
    public function evaluationCandidates(): HasMany
    {
        return $this->hasMany(EvaluationCandidate::class, 'quotation_revision_id');
    }

    /** @return HasMany<ClarificationThread, $this> */
    public function clarificationThreads(): HasMany
    {
        return $this->hasMany(ClarificationThread::class, 'quotation_revision_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'valid_until' => 'immutable_date',
            'delivery_days' => 'integer',
            'advance_percent' => 'decimal:2',
            'balance_due_days' => 'integer',
            'delivery_charge' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'submitted_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
