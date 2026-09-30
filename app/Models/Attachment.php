<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property-read RequestAttachment|null $requestLink
 * @property-read RevisionAttachment|null $revisionLink
 * @property-read MessageAttachment|null $messageLink
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

    /** @return HasOne<RequestAttachment, $this> */
    public function requestLink(): HasOne
    {
        return $this->hasOne(RequestAttachment::class, 'attachment_id');
    }

    /** @return HasOne<RevisionAttachment, $this> */
    public function revisionLink(): HasOne
    {
        return $this->hasOne(RevisionAttachment::class, 'attachment_id');
    }

    /** @return HasOne<MessageAttachment, $this> */
    public function messageLink(): HasOne
    {
        return $this->hasOne(MessageAttachment::class, 'attachment_id');
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
