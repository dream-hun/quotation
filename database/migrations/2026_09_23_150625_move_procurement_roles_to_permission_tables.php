<?php

declare(strict_types=1);

use App\Enums\ProcurementPermission;
use App\Enums\ProcurementRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        throw_unless(in_array(DB::getDriverName(), ['mysql', 'sqlite'], true), RuntimeException::class, 'Procurement integrity constraints support MySQL and SQLite tests.');

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (ProcurementPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (ProcurementRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions(array_map(
                fn (ProcurementPermission $permission): string => $permission->value, $role->permissions()));
        }

        if (Schema::hasTable('user_roles')) {
            DB::table('model_has_roles')->insertUsing(['role_id', 'model_type', 'model_id'], DB::table('user_roles')
                ->join('roles', fn (JoinClause $join): JoinClause => $join->on('roles.name', '=', 'user_roles.role_code')->where('roles.guard_name', 'web'))
                ->selectRaw('roles.id, ?, user_roles.user_id', [User::class]));

            foreach (['insert', 'update'] as $event) {
                DB::statement('DROP TRIGGER IF EXISTS supplier_members_guard_35_'.$event);
            }

            Schema::drop('user_roles');
        }

        $userType = DB::getPdo()->quote(User::class);

        foreach (['INSERT', 'UPDATE'] as $event) {
            $this->rejectWhen('model_has_roles', $event, 'model_has_roles_guard_roles_'.mb_strtolower($event), 'internal_roles_exclude_supplier_membership',
                "NEW.model_type = {$userType} AND EXISTS (SELECT 1 FROM supplier_members m WHERE m.user_id = NEW.model_id AND m.status = 'active')", 'NEW.model_id');
            $this->rejectWhen('supplier_members', $event, 'supplier_members_guard_roles_'.mb_strtolower($event), 'supplier_membership_excludes_internal_roles',
                "NEW.status = 'active' AND EXISTS (SELECT 1 FROM model_has_roles r WHERE r.model_id = NEW.user_id AND r.model_type = {$userType})", 'NEW.user_id');
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function rejectWhen(string $table, string $event, string $trigger, string $reason, string $condition, string $userKey): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN DECLARE locked_parent BIGINT; SELECT id INTO locked_parent FROM users WHERE id = {$userKey} FOR UPDATE; IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$reason}'; END IF; END");

            return;
        }

        DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, '{$reason}'); END");
    }
};
