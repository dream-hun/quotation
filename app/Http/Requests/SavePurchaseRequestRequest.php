<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SavePurchaseRequest;
use App\Concerns\ProcurementAccess;
use App\Enums\ProcurementPermission;
use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type Input from SavePurchaseRequest */
final class SavePurchaseRequestRequest extends FormRequest
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

        return ProcurementAccess::can($actor, ProcurementPermission::ManageRequests);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'delivery_destination' => ['nullable', 'string', 'max:2000'],
            'currency_code' => ['nullable', 'string', 'exists:currencies,code'],
            'deadline_at' => ['nullable', 'date'],
            'approver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'items' => ['present', 'array', 'max:100'],
            'items.*' => ['array:description,specification,quantity,unit'],
            'items.*.description' => ['required', 'string', 'max:2000'],
            'items.*.specification' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999999.9999'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'criteria' => ['present', 'array', 'max:40'],
            'criteria.*' => ['array:name,category,mandatory,assessment_rule'],
            'criteria.*.name' => ['required', 'string', 'max:160', 'distinct'],
            'criteria.*.category' => ['required', 'in:price,delivery,payment,specification'],
            'criteria.*.mandatory' => ['required', 'boolean'],
            'criteria.*.assessment_rule' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
