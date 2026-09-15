<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

final class ProcurementMutationRequest extends FormRequest
{
    /** @return list<string> */
    public function supplierUuids(): array
    {
        /** @var list<string> $suppliers */
        $suppliers = $this->validated('supplier_uuids', []);

        return $suppliers;
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
            'lock_version' => ['required', 'integer', 'min:0'],
            'operation' => ['nullable', 'in:extend,cancel,clone,decline,participate,withdraw,approve,return,reject'],
            'reason' => ['nullable', 'string', 'max:10000'],
            'deadline_at' => ['nullable', 'date'],
            'supplier_uuids' => ['sometimes', 'array', 'max:100'],
            'supplier_uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
