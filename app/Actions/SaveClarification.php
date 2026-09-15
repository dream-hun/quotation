<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\PurchaseRequest;
use App\Models\SupplierMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** @phpstan-type Input array{lock_version: int, thread_uuid?: ?string, supplier_uuid?: ?string, subject?: ?string, body?: ?string, operation?: ?string, visibility?: ?string, material?: ?bool} */
final readonly class SaveClarification
{
    public function __construct(private RecordProcurementEvent $audit, private NotifyProcurementUsers $notify) {}

    /** @param Input $data */
    public function handle(User $actor, PurchaseRequest $request, array $data): void
    {
        DB::transaction(function () use ($actor, $request, $data): void {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            ProcurementAccess::view($actor, $request);
            $buyer = ProcurementAccess::role($actor, 'buyer');
            $supplierId = ProcurementAccess::supplierId($actor);
            abort_unless($buyer || $supplierId !== null, 403);
            ProcurementGuard::require(in_array($request->status, ['open', 'closed', 'evaluating'], true), 'Clarifications are read-only in this state.');
            if (! empty($data['thread_uuid'])) {
                $thread = $request->clarificationThreads()->where('uuid', $data['thread_uuid'])->firstOrFail();
                abort_unless($buyer || ($thread->visibility === 'private' && $thread->supplier_id === $supplierId), 404);
                ProcurementGuard::version($thread, (int) $data['lock_version']);
                if (($data['operation'] ?? '') === 'resolve') {
                    abort_unless($buyer, 403);
                    $thread->update(['status' => 'resolved', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'lock_version' => $thread->lock_version + 1]);
                } else {
                    ProcurementGuard::require($thread->status === 'open', 'This thread is resolved.');
                    ProcurementGuard::require(filled($data['body'] ?? null), 'Enter a message.', 'body');
                    $thread->messages()->create(['author_id' => $actor->id, 'body' => $data['body']]);
                    $thread->increment('lock_version');
                }
            } else {
                ProcurementGuard::require(filled($data['subject'] ?? null) && filled($data['body'] ?? null), 'Enter a subject and message.');
                $visibility = $buyer && ($data['visibility'] ?? '') === 'all_invited' ? 'all_invited' : 'private';
                $targetSupplier = $buyer ? $request->invitations()->whereHas('supplier', fn ($query) => $query->where('uuid', $data['supplier_uuid'] ?? ''))->value('supplier_id') : $supplierId;
                ProcurementGuard::require($visibility === 'all_invited' || $targetSupplier !== null, 'Choose an invited supplier.');
                $thread = $request->clarificationThreads()->create([
                    'supplier_id' => $visibility === 'private' ? $targetSupplier : null, 'visibility' => $visibility,
                    'subject' => $data['subject'], 'status' => 'open', 'material' => $buyer ? (bool) ($data['material'] ?? true) : true,
                    'opened_by' => $actor->id,
                ]);
                $thread->messages()->create(['author_id' => $actor->id, 'body' => $data['body']]);
            }

            $recipients = SupplierMember::query()->where('status', 'active')
                ->when($thread->supplier_id !== null, fn ($query) => $query->where('supplier_id', $thread->supplier_id),
                    fn ($query) => $query->whereIn('supplier_id', $request->invitations()->select('supplier_id')))
                ->pluck('user_id')->all();
            $this->notify->handle($request, [...$recipients, $request->created_by], 'clarification.updated', 'thread:'.$thread->uuid.':'.$thread->lock_version);
            $this->audit->handle($actor, $request, 'clarification.updated', $thread);
        });
    }
}
