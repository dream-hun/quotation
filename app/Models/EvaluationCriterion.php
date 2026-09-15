<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\EvaluationCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $request_id
 * @property string $name
 * @property string $category
 * @property bool $mandatory
 * @property string $assessment_rule
 * @property int $display_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read PurchaseRequest $request
 * @property-read Collection<int, CriterionAssessment> $assessments
 */
#[Table(name: 'evaluation_criteria')]
final class EvaluationCriterion extends Model
{
    /** @use HasFactory<EvaluationCriterionFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    /** @return HasMany<CriterionAssessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(CriterionAssessment::class, 'criterion_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mandatory' => 'boolean',
            'display_order' => 'integer',
            'lock_version' => 'integer',
        ];
    }
}
