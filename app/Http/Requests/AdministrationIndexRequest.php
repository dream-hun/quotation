<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\ProcurementAccess;
use App\Enums\ProcurementPermission;
use App\Enums\ProcurementRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @phpstan-type Filters array{q?: ?string, status?: ?string, role?: ?string, supplier?: ?string} */
final class AdministrationIndexRequest extends FormRequest
{
    /** @return Filters */
    public function payload(): array
    {
        /** @var Filters $data */
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
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:active,disabled,inactive'],
            'supplier' => ['nullable', 'uuid'],
            'role' => ['nullable', Rule::in([...array_column(ProcurementRole::cases(), 'value'), 'supplier', 'none'])],
        ];
    }
}
