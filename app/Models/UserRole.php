<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Database\Factories\UserRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $role_code
 * @property int $assigned_by
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 * @property-read User $assignedBy
 */
final class UserRole extends Model
{
    /** @use HasFactory<UserRoleFactory> */
    use HasFactory;

    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

        ];
    }
}
