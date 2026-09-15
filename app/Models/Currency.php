<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property int $minor_digits
 * @property bool $active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read Collection<int, PurchaseRequest> $requests
 * @property-read Collection<int, Organization> $organizations
 */
#[Table(name: 'currencies')]
final class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return HasMany<PurchaseRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'currency_code', 'code');
    }

    /** @return HasMany<Organization, $this> */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'default_currency_code', 'code');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'minor_digits' => 'integer',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}
