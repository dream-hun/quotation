<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Currency;
use App\Models\Supplier;
use App\Models\User;

final readonly class ReadProcurementOptions
{
    /** @return array<string, mixed> */
    public function handle(bool $buyer, int $creatorId): array
    {
        if (! $buyer) {
            return ['currencies' => [], 'approvers' => [], 'suppliers' => []];
        }

        return [
            'currencies' => Currency::query()->where('active', true)->get(['code', 'name', 'minor_digits']),
            'approvers' => User::query()->where('status', 'active')->where('id', '!=', $creatorId)
                ->whereHas('roles', fn ($query) => $query->where('role_code', 'approver'))
                ->limit(100)->get(['uuid', 'name']),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('legal_name')->limit(100)->get(['uuid', 'legal_name']),
        ];
    }
}
