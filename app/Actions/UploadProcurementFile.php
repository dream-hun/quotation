<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Enums\ProcurementPermission;
use App\Models\Attachment;
use App\Models\PurchaseRequest;
use App\Models\QuotationRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class UploadProcurementFile
{
    public function __construct(private RecordProcurementEvent $audit) {}

    public function handle(User $actor, PurchaseRequest $request, UploadedFile $file, string $parentType, ?string $parentUuid): void
    {
        $storageKey = null;
        try {
            DB::transaction(function () use ($actor, $request, $file, $parentType, $parentUuid, &$storageKey): void {
                $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
                ProcurementAccess::view($actor, $request);
                $parent = $request;
                if ($parentType === 'request') {
                    ProcurementAccess::buyer($actor);
                    ProcurementGuard::require($request->status === 'draft', 'Published request documents cannot be changed.');
                } elseif ($parentType === 'revision') {
                    $supplierId = ProcurementAccess::supplier($actor, $request);
                    ProcurementGuard::open($request);
                    $parent = QuotationRevision::query()->where('uuid', $parentUuid)->whereHas('quotation.invitation',
                        fn ($query) => $query->where('request_id', $request->id)->where('supplier_id', $supplierId))->firstOrFail();
                    ProcurementGuard::require($parent->status === 'draft', 'Submitted documents cannot be replaced.');
                } else {
                    $thread = $request->clarificationThreads()->whereHas('messages', fn ($query) => $query->where('uuid', $parentUuid))->firstOrFail();
                    $supplierId = ProcurementAccess::supplierId($actor);
                    abort_unless(ProcurementAccess::can($actor, ProcurementPermission::ManageRequests) || ($supplierId !== null && $thread->supplier_id === $supplierId), 404);
                    ProcurementGuard::require(in_array($request->status, ['open', 'closed', 'evaluating'], true) && $thread->status === 'open', 'This conversation is read-only.');
                    $parent = $thread->messages()->where('uuid', $parentUuid)->where('author_id', $actor->id)->firstOrFail();
                }

                ProcurementGuard::require($parent->attachmentLinks()->count() < 10, 'At most ten files are permitted per record.');
                $storageKey = $file->store('procurement', 'local');
                ProcurementGuard::require(is_string($storageKey), 'Unable to store this file. Try again.');
                $attachment = Attachment::query()->create([
                    'storage_key' => $storageKey, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'detected_media_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
                    'content_hash' => hash_file('sha256', $file->getRealPath()), 'scan_status' => 'pending',
                    'uploaded_by' => $actor->id, 'uploaded_at' => now(),
                ]);
                $parent->attachmentLinks()->create(['attachment_id' => $attachment->id]);
                $this->audit->handle($actor, $request, 'file.uploaded', $attachment);
            });
        } catch (Throwable $throwable) {
            if (is_string($storageKey)) {
                Storage::disk('local')->delete($storageKey);
            }

            throw $throwable;
        }
    }
}
