<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\PurchaseRequest;
use App\Models\SupplierMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProcurementAccess
{
    public static function role(User $user, string $role): bool
    {
        return self::hasAnyRole($user, [$role]);
    }

    public static function supplierId(User $user): ?int
    {
        if ($user->status !== 'active') {
            return null;
        }

        return SupplierMember::query()->where('user_id', $user->id)->where('status', 'active')
            ->whereHas('supplier', fn (Builder $query) => $query->where('status', 'active'))->first()?->supplier_id;
    }

    public static function internal(User $user): bool
    {
        return self::hasAnyRole($user, ['buyer', 'approver', 'auditor']);
    }

    /** @return Builder<PurchaseRequest> */
    public static function requests(User $user): Builder
    {
        $query = PurchaseRequest::query();
        if (self::internal($user)) {
            return $query;
        }

        $supplierId = self::supplierId($user);

        return $query->where('status', '!=', 'draft')->whereHas('invitations',
            fn (Builder $invitation) => $invitation->where('supplier_id', $supplierId ?? 0));
    }

    public static function view(User $user, PurchaseRequest $request): void
    {
        abort_unless(self::requests($user)->whereKey($request->id)->exists(), 404);
    }

    public static function buyer(User $user): void
    {
        abort_unless(self::role($user, 'buyer'), 403);
    }

    public static function supplier(User $user, PurchaseRequest $request): int
    {
        self::view($user, $request);
        $supplierId = self::supplierId($user);
        abort_if($supplierId === null, 403);

        return $supplierId;
    }

    public static function commercial(User $user, PurchaseRequest $request): bool
    {
        return self::internal($user) && $request->published_at !== null
            && $request->deadline_at !== null && ProcurementGuard::clock()->greaterThanOrEqualTo($request->deadline_at);
    }

    /** @param list<string> $roles */
    private static function hasAnyRole(User $user, array $roles): bool
    {
        return $user->status === 'active' && $user->roles()->whereIn('role_code', $roles)->exists()
            && ! $user->supplierMembership()->where('status', 'active')->exists();
    }
}
