<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final readonly class RecordProcurementEvent
{
    /** @param array<string, mixed> $details */
    public function handle(?User $actor, ?PurchaseRequest $request, string $action, Model $target, array $details = []): void
    {
        AuditEvent::query()->create([
            'request_id' => $request?->id, 'actor_user_id' => $actor?->id,
            'actor_label' => $actor instanceof User ? $actor->name : 'Procurement scheduler',
            'action' => $action, 'target_type' => class_basename($target),
            'target_id' => $target->getAttribute('uuid'), 'occurred_at' => now(),
            'correlation_id' => (string) Str::uuid(), 'safe_details' => $details,
        ]);
    }
}
