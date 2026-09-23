<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\ProcurementAccess;
use App\Concerns\UploadChunks;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

final class StoreUploadChunkRequest extends FormRequest
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
            'upload_uuid' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:'.(UploadChunks::maxChunks() - 1)],
            'chunk' => ['required', 'file', 'max:'.config()->integer('procurement.upload_chunk_kilobytes')],
        ];
    }
}
