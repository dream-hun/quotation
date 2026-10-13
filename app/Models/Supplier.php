<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $legal_name
 * @property string|null $registration_number
 * @property string $contact_email
 * @property string|null $phone
 * @property string|null $address
 * @property string $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Invitation> $invitations
 * @property-read Collection<int, ClarificationThread> $clarificationThreads
 */
final class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return HasMany<User, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'supplier_id');
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'supplier_id');
    }

    /** @return HasMany<ClarificationThread, $this> */
    public function clarificationThreads(): HasMany
    {
        return $this->hasMany(ClarificationThread::class, 'supplier_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }
}
