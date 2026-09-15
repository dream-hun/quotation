<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\AssessCandidate;
use App\Concerns\ProcurementAccess;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type Input from AssessCandidate */
final class AssessCandidateRequest extends FormRequest
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
            'lock_version' => ['required', 'integer', 'min:1'],
            'candidate_uuid' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:10000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*' => ['array:uuid,result,finding'],
            'lines.*.uuid' => ['required', 'uuid', 'distinct'],
            'lines.*.result' => ['required', 'in:pending,pass,fail'],
            'lines.*.finding' => ['required', 'string', 'max:5000'],
            'criteria' => ['present', 'array', 'max:40'],
            'criteria.*' => ['array:uuid,result,finding'],
            'criteria.*.uuid' => ['required', 'uuid', 'distinct'],
            'criteria.*.result' => ['required', 'in:pending,pass,fail'],
            'criteria.*.finding' => ['required', 'string', 'max:5000'],
        ];
    }
}
