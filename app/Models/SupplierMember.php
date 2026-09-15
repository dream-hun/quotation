<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $supplier_id
 * @property string $status
 * @property int $assigned_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $lock_version
 * @property-read User $user
 * @property-read Supplier $supplier
 * @property-read User $assignedBy
 */
final class SupplierMember extends Model
{
    /** @use HasFactory<SupplierMemberFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }
}
