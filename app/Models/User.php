<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Concerns\HasUuidRouteKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $normalized_email
 * @property string $status
 * @property int $lock_version
 * @property-read SupplierMember|null $supplierMembership
 * @property-read Collection<int, PurchaseRequest> $purchaseRequests
 * @property-read Collection<int, ApprovalCase> $approvalCases
 * @property-read Collection<int, ProcurementNotification> $procurementNotifications
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
final class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuidRouteKey;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /** @return HasOne<SupplierMember, $this> */
    public function supplierMembership(): HasOne
    {
        return $this->hasOne(SupplierMember::class);
    }

    /** @return HasMany<PurchaseRequest, $this> */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'created_by');
    }

    /** @return HasMany<ApprovalCase, $this> */
    public function approvalCases(): HasMany
    {
        return $this->hasMany(ApprovalCase::class, 'assigned_approver_id');
    }

    /** @return HasMany<ProcurementNotification, $this> */
    public function procurementNotifications(): HasMany
    {
        return $this->hasMany(ProcurementNotification::class, 'recipient_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
