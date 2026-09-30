<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssembleUploadChunks;
use App\Actions\DownloadProcurementFile;
use App\Actions\StoreUploadChunk;
use App\Actions\UploadProcurementFile;
use App\Http\Requests\StoreUploadChunkRequest;
use App\Http\Requests\UploadProcurementFileRequest;
use App\Models\Attachment;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementFileController extends Controller
{
    public function store(UploadProcurementFileRequest $request, PurchaseRequest $purchaseRequest, UploadProcurementFile $upload, AssembleUploadChunks $assemble): RedirectResponse
    {
        $document = $request->document($assemble);
        try {
            $upload->handle(($request->user() ?? abort(401)), $purchaseRequest, $document, $request->string('parent_type')->toString(), $request->filled('parent_uuid') ? $request->string('parent_uuid')->toString() : null);
        } finally {
            if ($request->filled('upload_uuid')) {
                File::delete($document->getPathname());
            }
        }

        return back()->with('success', 'File uploaded successfully.');
    }

    public function chunk(StoreUploadChunkRequest $request, PurchaseRequest $purchaseRequest, StoreUploadChunk $store): Response
    {
        $store->handle(($request->user() ?? abort(401)), $request->string('upload_uuid')->toString(), $request->integer('chunk_index'), $request->file('chunk'));

        return response()->noContent();
    }

    public function download(Request $request, Attachment $attachment, DownloadProcurementFile $download): StreamedResponse
    {
        return $download->handle(($request->user() ?? abort(401)), $attachment);
    }
}
