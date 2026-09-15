<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\ClarificationThreadFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $request_id
 * @property int|null $supplier_id
 * @property int|null $quotation_revision_id
 * @property string $visibility
 * @property string $subject
 * @property string $status
 * @property bool $material
 * @property int $opened_by
 * @property int|null $resolved_by
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read PurchaseRequest $request
 * @property-read Supplier|null $supplier
 * @property-read QuotationRevision|null $quotationRevision
 * @property-read User $openedBy
 * @property-read User|null $resolvedBy
 * @property-read Collection<int, ClarificationMessage> $messages
 */
final class ClarificationThread extends Model
{
    /** @use HasFactory<ClarificationThreadFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function quotationRevision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<ClarificationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ClarificationMessage::class, 'thread_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'material' => 'boolean',
            'resolved_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
