<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\PurchaseRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ProcurementGuard
{
    /** @phpstan-assert true $condition */
    public static function require(bool $condition, string $message, string $field = 'workflow'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public static function version(Model $record, int $version): void
    {
        self::require($record->getAttribute('lock_version') === $version,
            'This record changed. Reload it before saving again.', 'lock_version');
    }

    public static function clock(): CarbonImmutable
    {
        if (app()->runningUnitTests()) {
            return CarbonImmutable::now();
        }

        $result = DB::selectOne(DB::getDriverName() === 'mysql'
            ? 'SELECT UTC_TIMESTAMP(6) AS database_time' : 'SELECT CURRENT_TIMESTAMP AS database_time');

        throw_if(! is_object($result) || ! isset($result->database_time) || ! is_string($result->database_time), RuntimeException::class, 'Unable to obtain the authoritative database time.');

        return CarbonImmutable::parse($result->database_time, 'UTC');
    }

    public static function open(PurchaseRequest $request): void
    {
        self::require($request->status === 'open' && $request->deadline_at !== null
            && self::clock()->lessThan($request->deadline_at), 'The submission deadline has passed or this request is not open.');
    }

    public static function approver(PurchaseRequest $request, ?int $preparer = null): void
    {
        $approver = User::query()->find($request->assigned_approver_id);
        self::require($approver !== null && ProcurementAccess::role($approver, 'approver')
            && $approver->id !== $request->created_by && $approver->id !== $preparer,
            'Assign an active approver who is independent of the creator and evaluator.', 'assigned_approver_id');
    }
}
