<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property int $request_id
 * @property int $supplier_id
 * @property int $invited_by
 * @property CarbonImmutable $invited_at
 * @property CarbonImmutable|null $first_viewed_at
 * @property CarbonImmutable|null $declined_at
 * @property string|null $decline_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read PurchaseRequest $request
 * @property-read Supplier $supplier
 * @property-read User $invitedBy
 * @property-read Quotation|null $quotation
 */
final class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
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

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return HasOne<Quotation, $this> */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class, 'invitation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invited_at' => 'immutable_datetime',
            'first_viewed_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
