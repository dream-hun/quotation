<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ProcurementNotification;
use App\Models\PurchaseRequest;
use App\Models\User;
use InvalidArgumentException;

final readonly class NotifyProcurementUsers
{
    /** @param iterable<array-key, mixed> $recipients */
    public function handle(PurchaseRequest $request, iterable $recipients, string $event, string $key): void
    {
        foreach ($recipients as $recipientId) {
            throw_unless(is_int($recipientId), InvalidArgumentException::class, 'Notification recipients must be user identifiers.');

            if (! User::query()->whereKey($recipientId)->where('status', 'active')->exists()) {
                continue;
            }

            ProcurementNotification::query()->firstOrCreate(
                ['recipient_id' => $recipientId, 'event_key' => $key],
                ['request_id' => $request->id, 'event_type' => $event,
                    'safe_payload' => ['request_number' => $request->request_number],
                    'email_status' => 'pending', 'email_attempts' => 0, 'next_attempt_at' => now()],
            );
        }
    }
}
