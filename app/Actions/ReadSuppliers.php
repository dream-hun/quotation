<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Requests\AdministrationIndexRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

/** @phpstan-import-type Filters from AdministrationIndexRequest */
final readonly class ReadSuppliers
{
    /**
     * @param  Filters  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $suppliers = Supplier::query()
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('legal_name', 'like', '%'.$search.'%')->orWhere('contact_email', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->withCount(['members' => fn (Builder $query) => $query->where('supplier_membership_status', 'active'), 'invitations'])
            ->orderBy('legal_name')->orderBy('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Supplier $supplier): array => $supplier->only(['uuid', 'legal_name', 'contact_email', 'status', 'lock_version']) + [
                'members_count' => $supplier->members_count,
                'invitations_count' => $supplier->invitations_count,
            ]);

        return ['suppliers' => $suppliers, 'filters' => $filters];
    }
}
