<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DownloadProcurementFile;
use App\Actions\UploadProcurementFile;
use App\Http\Requests\UploadProcurementFileRequest;
use App\Models\Attachment;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementFileController extends Controller
{
    public function store(UploadProcurementFileRequest $request, PurchaseRequest $purchaseRequest, UploadProcurementFile $upload): RedirectResponse
    {
        $upload->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->file('file'), $request->string('parent_type')->toString(), $request->filled('parent_uuid') ? $request->string('parent_uuid')->toString() : null);

        return back()->with('success', 'File uploaded and awaiting security checks.');
    }

    public function download(Request $request, Attachment $attachment, DownloadProcurementFile $download): StreamedResponse
    {
        return $download->handle(($request->user() ?? abort(401)), $attachment);
    }
}
