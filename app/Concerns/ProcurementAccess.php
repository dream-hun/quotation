<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\ProcurementPermission;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProcurementAccess
{
    /**
     * Whether an active internal user holds the permission through one of their roles.
     *
     * Roles are read from the database on every call instead of the loaded relation or
     * permission cache, so a revocation takes effect immediately for long-lived user instances.
     */
    public static function can(User $user, ProcurementPermission $permission): bool
    {
        return $user->status === 'active'
            && $user->roles()->whereHas('permissions', fn (Builder $query) => $query->where('name', $permission->value))->exists()
            && ! User::query()->whereKey($user->id)->whereNotNull('supplier_id')->where('supplier_membership_status', 'active')->exists();
    }

    public static function supplierId(User $user): ?int
    {
        if ($user->status !== 'active') {
            return null;
        }

        return User::query()->whereKey($user->id)->where('status', 'active')->where('supplier_membership_status', 'active')
            ->whereHas('supplier', fn (Builder $query) => $query->where('status', 'active'))->first()?->supplier_id;
    }

    public static function internal(User $user): bool
    {
        return self::can($user, ProcurementPermission::ViewAllRequests);
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
        abort_unless(self::can($user, ProcurementPermission::ManageRequests), 403);
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
}
