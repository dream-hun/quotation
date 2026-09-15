<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveQuotation;
use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type Input from SaveQuotation */
final class SaveQuotationRequest extends FormRequest
{
    /** @return Input */
    public function payload(): array
    {
        /** @var Input $data */
        $data = $this->validated();

        return $data;
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
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'payment_terms' => ['nullable', 'string', 'max:10000'],
            'advance_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'balance_due_days' => ['nullable', 'integer', 'between:0,3650'],
            'delivery_charge' => ['nullable', 'regex:/^\d{1,16}(?:\.\d{1,4})?$/D'],
            'lines' => ['required', 'array', 'max:100'],
            'lines.*' => ['array:item_uuid,offered_description,unit_price,discount_amount,tax_amount,supplier_compliance_response,zero_price_reason'],
            'lines.*.item_uuid' => ['required', 'uuid', 'distinct'],
            'lines.*.offered_description' => ['nullable', 'string', 'max:5000'],
            'lines.*.unit_price' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/D'],
            'lines.*.discount_amount' => ['nullable', 'regex:/^\d{1,16}(?:\.\d{1,4})?$/D'],
            'lines.*.tax_amount' => ['nullable', 'regex:/^\d{1,16}(?:\.\d{1,4})?$/D'],
            'lines.*.supplier_compliance_response' => ['nullable', 'string', 'max:5000'],
            'lines.*.zero_price_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
