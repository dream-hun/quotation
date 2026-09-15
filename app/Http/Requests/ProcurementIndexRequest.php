<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\ReadProcurementIndex;
use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @phpstan-import-type Input from ReadProcurementIndex */
final class ProcurementIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:200'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:draft,open,closed,evaluating,pending_approval,awarded,rejected,cancelled'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), ['after_or_equal:from'])],
        ];
    }
}
