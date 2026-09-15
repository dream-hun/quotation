<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\ClarificationMessageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $thread_id
 * @property int $author_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property-read ClarificationThread $thread
 * @property-read User $author
 * @property-read Collection<int, MessageAttachment> $attachmentLinks
 */
final class ClarificationMessage extends Model
{
    /** @use HasFactory<ClarificationMessageFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    /** @return BelongsTo<ClarificationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ClarificationThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachmentLinks(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [

        ];
    }
}
