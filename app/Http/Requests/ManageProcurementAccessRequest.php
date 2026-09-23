<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\ManageProcurementAccess;
use App\Concerns\ProcurementAccess;
use App\Enums\ProcurementPermission;
use App\Enums\ProcurementRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @phpstan-import-type Input from ManageProcurementAccess */
final class ManageProcurementAccessRequest extends FormRequest
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

        return ProcurementAccess::can($actor, ProcurementPermission::ManageAccess);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:user,supplier,organization,currency'],
            'uuid' => ['nullable', 'uuid'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'name' => ['required_unless:type,supplier', 'string', 'max:160'],
            'email' => ['required_if:type,user', 'email', 'max:254', Rule::unique('users', 'email')->ignore(User::query()->where('uuid', $this->input('uuid'))->value('id'))],
            'status' => ['required_if:type,user,supplier', $this->input('type') === 'supplier' ? 'in:active,inactive' : 'in:active,disabled'],
            'roles' => ['sometimes', 'array', 'max:'.count(ProcurementRole::cases())],
            'roles.*' => ['required', Rule::enum(ProcurementRole::class), 'distinct'],
            'supplier_uuid' => ['nullable', 'uuid'],
            'replacement_uuid' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:10000'],
            'legal_name' => ['required_if:type,supplier', 'string', 'max:200'],
            'contact_email' => ['required_if:type,supplier', 'email', 'max:254'],
            'timezone' => ['required_if:type,organization', 'timezone:all'],
            'default_currency_code' => ['required_if:type,organization', 'exists:currencies,code'],
            'code' => ['required_if:type,currency', 'regex:/^[A-Z]{3}$/D'],
            'minor_digits' => ['required_if:type,currency', 'integer', 'between:0,4'],
            'active' => ['required_if:type,currency', 'boolean'],
        ];
    }
}
