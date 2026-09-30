<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadProcurementFile
{
    public function __construct(private RecordProcurementEvent $audit) {}

    public function handle(User $actor, Attachment $attachment): StreamedResponse
    {
        $attachment->load(['requestLink.request', 'revisionLink.revision.quotation.invitation', 'messageLink.message.thread']);
        if ($attachment->requestLink !== null) {
            $request = $attachment->requestLink->request;
            ProcurementAccess::view($actor, $request);
        } elseif ($attachment->revisionLink !== null) {
            $revision = $attachment->revisionLink->revision;
            $invitation = $revision->quotation->invitation;
            $request = $invitation->request()->firstOrFail();
            ProcurementAccess::view($actor, $request);
            abort_unless(ProcurementAccess::supplierId($actor) === $invitation->supplier_id
                || ($revision->status === 'submitted' && ProcurementAccess::commercial($actor, $request)), 404);
        } elseif ($attachment->messageLink !== null) {
            $thread = $attachment->messageLink->message->thread;
            $request = $thread->request()->firstOrFail();
            ProcurementAccess::view($actor, $request);
            abort_unless(ProcurementAccess::internal($actor) || $thread->visibility === 'all_invited'
                || ProcurementAccess::supplierId($actor) === $thread->supplier_id, 404);
        } else {
            abort(404);
        }

        abort_unless(Storage::disk('local')->exists($attachment->storage_key), 404);
        DB::transaction(fn () => $this->audit->handle($actor, $request, 'file.downloaded', $attachment));

        return Storage::disk('local')->download($attachment->storage_key, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
