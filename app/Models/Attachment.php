<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $storage_key
 * @property string $original_name
 * @property string $detected_media_type
 * @property int $size_bytes
 * @property string $content_hash
 * @property int $uploaded_by
 * @property CarbonImmutable $uploaded_at
 * @property CarbonImmutable|null $created_at
 * @property-read User $uploadedBy
 * @property int|null $request_id
 * @property-read PurchaseRequest|null $request
 * @property int|null $revision_id
 * @property-read QuotationRevision|null $revision
 * @property int|null $message_id
 * @property-read ClarificationMessage|null $message
 */
final class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'revision_id');
    }

    /** @return BelongsTo<ClarificationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ClarificationMessage::class, 'message_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'immutable_datetime',
        ];
    }
}
