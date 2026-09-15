<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

final class UploadProcurementFileRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'min:0.001', 'max:10240', 'mimes:pdf,docx,xlsx,png,jpg,jpeg', 'extensions:pdf,docx,xlsx,png,jpg,jpeg'],
            'parent_type' => ['required', 'in:request,revision,message'],
            'parent_uuid' => ['nullable', 'uuid', 'required_unless:parent_type,request'],
        ];
    }
}
