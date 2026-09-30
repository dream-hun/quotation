<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProcurementRole;
use App\Http\Requests\AdministrationIndexRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** @phpstan-import-type Filters from AdministrationIndexRequest */
final readonly class ReadUserAccounts
{
    /**
     * @param  Filters  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $role = $filters['role'] ?? null;
        $users = User::query()
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier'] ?? null, fn (Builder $query, string $supplier) => $query->whereHas('supplierMembership.supplier', fn (Builder $query) => $query->where('uuid', $supplier)))
            ->when($role === 'supplier', fn (Builder $query) => $query->has('supplierMembership'))
            ->when($role === 'none', fn (Builder $query) => $query->doesntHave('roles')->doesntHave('supplierMembership'))
            ->when(ProcurementRole::tryFrom($role ?? ''), fn (Builder $query, ProcurementRole $internal) => $query->role($internal->value))
            ->with(['roles', 'supplierMembership.supplier'])
            ->orderBy('name')->orderBy('id')
            ->paginate(20)->withQueryString()
            ->through(fn (User $user): array => $user->only(['uuid', 'name', 'email', 'status', 'lock_version']) + [
                'roles' => $user->roles->pluck('name'),
                'supplier_uuid' => $user->supplierMembership?->supplier->uuid,
                'supplier_name' => $user->supplierMembership?->supplier->legal_name,
            ]);

        return [
            'users' => $users,
            'filters' => $filters,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('legal_name')->get(['uuid', 'legal_name']),
            'approvers' => User::query()->role('approver')->where('status', 'active')->orderBy('name')->get(['uuid', 'name']),
        ];
    }
}
