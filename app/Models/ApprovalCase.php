<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\ApprovalCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property int $round_id
 * @property int $assigned_approver_id
 * @property string $status
 * @property CarbonImmutable $requested_at
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read EvaluationRound $round
 * @property-read User $assignedApprover
 * @property-read User|null $decidedBy
 * @property-read Award|null $award
 */
final class ApprovalCase extends Model
{
    /** @use HasFactory<ApprovalCaseFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<EvaluationRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(EvaluationRound::class, 'round_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_approver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasOne<Award, $this> */
    public function award(): HasOne
    {
        return $this->hasOne(Award::class, 'approval_case_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
