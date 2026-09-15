<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\LineAssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $candidate_id
 * @property int $quotation_line_id
 * @property string $result
 * @property string $evidence_note
 * @property int $assessed_by
 * @property CarbonImmutable $assessed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read EvaluationCandidate $candidate
 * @property-read QuotationLine $quotationLine
 * @property-read User $assessedBy
 */
final class LineAssessment extends Model
{
    /** @use HasFactory<LineAssessmentFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<EvaluationCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(EvaluationCandidate::class, 'candidate_id');
    }

    /** @return BelongsTo<QuotationLine, $this> */
    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class, 'quotation_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
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
