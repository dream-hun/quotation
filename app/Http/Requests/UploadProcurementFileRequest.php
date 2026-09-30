<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\AssembleUploadChunks;
use App\Concerns\ProcurementAccess;
use App\Concerns\UploadChunks;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UploadProcurementFileRequest extends FormRequest
{
    /**
     * The uploaded document, either sent directly or assembled from chunks.
     * An assembled document is a temporary file that the caller must delete.
     */
    public function document(AssembleUploadChunks $assemble): UploadedFile
    {
        $file = $this->file('file');
        if ($file instanceof UploadedFile) {
            return $file;
        }

        $user = $this->user() ?? abort(401);
        $file = $assemble->handle($user, $this->string('upload_uuid')->toString(), $this->integer('total_chunks'), $this->string('file_name')->toString());
        try {
            Validator::make(['file' => $file], ['file' => $this->documentRules()])->validate();
        } catch (ValidationException $validationException) {
            File::delete($file->getPathname());

            throw $validationException;
        }

        return $file;
    }

    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null || $actor->status !== 'active') {
            return false;
        }

        $record = $this->route('purchaseRequest');
        if ($record instanceof PurchaseRequest) {
            ProcurementAccess::view($actor, $record);
        }

        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required_without:upload_uuid', 'prohibits:upload_uuid', ...$this->documentRules()],
            'upload_uuid' => ['required_without:file', 'uuid'],
            'total_chunks' => ['required_with:upload_uuid', 'integer', 'min:1', 'max:'.UploadChunks::maxChunks()],
            'file_name' => ['required_with:upload_uuid', 'string', 'max:255'],
            'parent_type' => ['required', 'in:request,revision,message'],
            'parent_uuid' => ['nullable', 'uuid', 'required_unless:parent_type,request'],
        ];
    }

    /** @return list<string> */
    private function documentRules(): array
    {
        return ['file', 'min:0.001', 'max:'.config()->integer('procurement.max_upload_kilobytes'), 'mimes:pdf,docx,xlsx,png,jpg,jpeg', 'extensions:pdf,docx,xlsx,png,jpg,jpeg'];
    }
}
