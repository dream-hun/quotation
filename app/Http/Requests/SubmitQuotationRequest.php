<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitQuotationRequest extends FormRequest
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
            'lock_version' => ['required', 'integer', 'min:1'],
            'revision_uuid' => ['required', 'uuid'],
            'submission_key' => ['required', 'uuid'],
        ];
    }
}
