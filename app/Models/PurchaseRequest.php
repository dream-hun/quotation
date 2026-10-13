<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $request_number
 * @property string $title
 * @property string|null $description
 * @property string|null $delivery_destination
 * @property string|null $currency_code
 * @property int|null $currency_precision
 * @property CarbonImmutable|null $deadline_at
 * @property string $timezone
 * @property string $status
 * @property int $created_by
 * @property int|null $assigned_approver_id
 * @property int|null $cloned_from_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read Organization $organization
 * @property-read Currency|null $currency
 * @property-read User $creator
 * @property-read User|null $assignedApprover
 * @property-read PurchaseRequest|null $clonedFrom
 * @property-read Collection<int, RequestItem> $items
 * @property-read Collection<int, EvaluationCriterion> $criteria
 * @property-read Collection<int, Invitation> $invitations
 * @property-read Collection<int, EvaluationRound> $evaluationRounds
 * @property-read Award|null $award
 * @property-read Collection<int, ClarificationThread> $clarificationThreads
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, AuditEvent> $auditEvents
 * @property-read Collection<int, ProcurementNotification> $notifications
 * @property-read Collection<int, PurchaseRequest> $clones
 */
final class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_approver_id');
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    /** @return HasMany<RequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id');
    }

    /** @return HasMany<EvaluationCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class, 'request_id');
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'request_id');
    }

    /** @return HasMany<EvaluationRound, $this> */
    public function evaluationRounds(): HasMany
    {
        return $this->hasMany(EvaluationRound::class, 'request_id');
    }

    /** @return HasOne<Award, $this> */
    public function award(): HasOne
    {
        return $this->hasOne(Award::class, 'request_id');
    }

    /** @return HasMany<ClarificationThread, $this> */
    public function clarificationThreads(): HasMany
    {
        return $this->hasMany(ClarificationThread::class, 'request_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'request_id');
    }

    /** @return HasMany<AuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'request_id');
    }

    /** @return HasMany<ProcurementNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(ProcurementNotification::class, 'request_id');
    }

    /** @return HasMany<PurchaseRequest, $this> */
    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'cloned_from_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency_precision' => 'integer',
            'deadline_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
