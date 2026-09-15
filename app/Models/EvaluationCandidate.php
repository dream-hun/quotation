<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\EvaluationCandidateFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $round_id
 * @property int $quotation_revision_id
 * @property string $disposition
 * @property string|null $disposition_reason
 * @property CarbonImmutable|null $assessed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read EvaluationRound $round
 * @property-read QuotationRevision $quotationRevision
 * @property-read Collection<int, LineAssessment> $lineAssessments
 * @property-read Collection<int, CriterionAssessment> $criterionAssessments
 */
final class EvaluationCandidate extends Model
{
    /** @use HasFactory<EvaluationCandidateFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<EvaluationRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(EvaluationRound::class, 'round_id');
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function quotationRevision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /** @return HasMany<LineAssessment, $this> */
    public function lineAssessments(): HasMany
    {
        return $this->hasMany(LineAssessment::class, 'candidate_id');
    }

    /** @return HasMany<CriterionAssessment, $this> */
    public function criterionAssessments(): HasMany
    {
        return $this->hasMany(CriterionAssessment::class, 'candidate_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assessed_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
