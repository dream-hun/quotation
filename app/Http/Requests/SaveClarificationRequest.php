<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveClarification;
use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type Input from SaveClarification */
final class SaveClarificationRequest extends FormRequest
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
            'thread_uuid' => ['nullable', 'uuid'],
            'supplier_uuid' => ['nullable', 'uuid'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:10000'],
            'operation' => ['nullable', 'in:reply,resolve'],
            'visibility' => ['nullable', 'in:private,all_invited'],
            'material' => ['nullable', 'boolean'],
        ];
    }
}
