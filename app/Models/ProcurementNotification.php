<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\ProcurementNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $recipient_id
 * @property int|null $request_id
 * @property string $event_key
 * @property string $event_type
 * @property array<string, mixed> $safe_payload
 * @property CarbonImmutable|null $read_at
 * @property string $email_status
 * @property int $email_attempts
 * @property CarbonImmutable|null $next_attempt_at
 * @property string|null $last_error_code
 * @property CarbonImmutable|null $email_sent_at
 * @property CarbonImmutable|null $claimed_until
 * @property CarbonImmutable|null $created_at
 * @property-read User $recipient
 * @property-read PurchaseRequest|null $request
 */
final class ProcurementNotification extends Model
{
    /** @use HasFactory<ProcurementNotificationFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'safe_payload' => 'array',
            'read_at' => 'immutable_datetime',
            'email_attempts' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'email_sent_at' => 'immutable_datetime',
            'claimed_until' => 'immutable_datetime',
        ];
    }
}
