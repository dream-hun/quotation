<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\RevisionAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $attachment_id
 * @property int $revision_id
 * @property CarbonImmutable|null $created_at
 * @property-read Attachment $attachment
 * @property-read QuotationRevision $revision
 */
final class RevisionAttachment extends Model
{
    /** @use HasFactory<RevisionAttachmentFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'revision_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [

        ];
    }
}
