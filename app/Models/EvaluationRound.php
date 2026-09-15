<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\EvaluationRoundFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property-read int|null $draft_request_id
 * @property int $request_id
 * @property int $round_number
 * @property string $status
 * @property int $prepared_by
 * @property int|null $recommended_candidate_id
 * @property string|null $recommendation_reason
 * @property string|null $participation_justification
 * @property array<string, mixed>|null $frozen_snapshot
 * @property string|null $calculation_version
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read PurchaseRequest $request
 * @property-read User $preparedBy
 * @property-read EvaluationCandidate|null $recommendedCandidate
 * @property-read Collection<int, EvaluationCandidate> $candidates
 * @property-read ApprovalCase|null $approvalCase
 */
final class EvaluationRound extends Model
{
    /** @use HasFactory<EvaluationRoundFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<EvaluationCandidate, $this> */
    public function recommendedCandidate(): BelongsTo
    {
        return $this->belongsTo(EvaluationCandidate::class, 'recommended_candidate_id');
    }

    /** @return HasMany<EvaluationCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(EvaluationCandidate::class, 'round_id');
    }

    /** @return HasOne<ApprovalCase, $this> */
    public function approvalCase(): HasOne
    {
        return $this->hasOne(ApprovalCase::class, 'round_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'frozen_snapshot' => 'array',
            'submitted_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
