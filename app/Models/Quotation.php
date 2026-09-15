<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property int $invitation_id
 * @property CarbonImmutable|null $withdrawn_at
 * @property int|null $withdrawn_by
 * @property string|null $withdrawal_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read Invitation $invitation
 * @property-read User|null $withdrawnBy
 * @property-read Collection<int, QuotationRevision> $revisions
 * @property-read QuotationRevision|null $latestSubmittedRevision
 */
final class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<Invitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class, 'invitation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    /** @return HasMany<QuotationRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuotationRevision::class, 'quotation_id');
    }

    /** @return HasOne<QuotationRevision, $this> */
    public function latestSubmittedRevision(): HasOne
    {
        return $this->hasOne(QuotationRevision::class, 'quotation_id')->ofMany(
            ['revision_number' => 'max'],
            fn (Builder $query) => $query->where('status', 'submitted'),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
