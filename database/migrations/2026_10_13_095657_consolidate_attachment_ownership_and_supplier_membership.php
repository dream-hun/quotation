<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        throw_unless(in_array(DB::getDriverName(), ['mysql', 'sqlite'], true), RuntimeException::class, 'Procurement integrity constraints support MySQL and SQLite tests.');

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->enum('supplier_membership_status', ['active', 'inactive'])->nullable();
            $table->foreignId('supplier_assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('supplier_assigned_at')->nullable();
            $table->index(['supplier_id', 'supplier_membership_status']);
        });

        Schema::table('attachments', function (Blueprint $table): void {
            $table->foreignId('request_id')->nullable()->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('revision_id')->nullable()->constrained('quotation_revisions')->restrictOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('clarification_messages')->restrictOnDelete();
        });

        DB::table('supplier_members')->orderBy('id')->chunkById(500, function ($members): void {
            foreach ($members as $member) {
                DB::table('users')->where('id', $member->user_id)->update([
                    'supplier_id' => $member->supplier_id,
                    'supplier_membership_status' => $member->status,
                    'supplier_assigned_by' => $member->assigned_by,
                    'supplier_assigned_at' => $member->created_at,
                ]);
            }
        });

        foreach (['request_attachments' => 'request_id', 'revision_attachments' => 'revision_id', 'message_attachments' => 'message_id'] as $table => $column) {
            DB::table($table)->orderBy('id')->chunkById(500, function ($links) use ($column): void {
                foreach ($links as $link) {
                    DB::table('attachments')->where('id', $link->attachment_id)->update([$column => $link->$column]);
                }
            });
        }

        $this->dropLegacyTriggers();
        $this->protectSupplierAssignments();
        $this->protectAttachments();

        foreach (['request_attachments', 'revision_attachments', 'message_attachments', 'supplier_members'] as $table) {
            Schema::drop($table);
        }
    }

    private function dropLegacyTriggers(): void
    {
        $triggers = DB::getDriverName() === 'mysql'
            ? DB::select('SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS owner FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')
            : DB::select("SELECT name, tbl_name AS owner FROM sqlite_master WHERE type = 'trigger'");

        foreach ($triggers as $trigger) {
            throw_unless(is_object($trigger) && isset($trigger->owner, $trigger->name) && is_string($trigger->owner) && is_string($trigger->name), UnexpectedValueException::class, 'Invalid database trigger metadata.');

            if (in_array($trigger->owner, ['request_attachments', 'revision_attachments', 'message_attachments', 'supplier_members'], true)
                || in_array($trigger->name, ['attachments_guard_107_update', 'model_has_roles_guard_roles_insert', 'model_has_roles_guard_roles_update'], true)) {
                $name = str_replace('`', '``', $trigger->name);
                DB::statement("DROP TRIGGER IF EXISTS `{$name}`");
            }
        }
    }

    private function protectSupplierAssignments(): void
    {
        $userType = DB::getPdo()->quote('App\\Models\\User');
        foreach (['INSERT', 'UPDATE'] as $event) {
            $this->rejectWhen('users', $event, 'supplier_assignment_shape',
                '(NEW.supplier_id IS NULL AND (NEW.supplier_membership_status IS NOT NULL OR NEW.supplier_assigned_by IS NOT NULL OR NEW.supplier_assigned_at IS NOT NULL)) OR (NEW.supplier_id IS NOT NULL AND (NEW.supplier_membership_status IS NULL OR NEW.supplier_assigned_by IS NULL))');
            $this->rejectWhen('users', $event, 'supplier_membership_excludes_internal_roles',
                "NEW.supplier_id IS NOT NULL AND NEW.supplier_membership_status = 'active' AND EXISTS (SELECT 1 FROM model_has_roles r WHERE r.model_id = NEW.id AND r.model_type = {$userType})");
            $this->rejectWhen('model_has_roles', $event, 'internal_roles_exclude_supplier_membership',
                "NEW.model_type = {$userType} AND EXISTS (SELECT 1 FROM users u WHERE u.id = NEW.model_id AND u.supplier_id IS NOT NULL AND u.supplier_membership_status = 'active')",
                'NEW.model_id');
        }
    }

    private function protectAttachments(): void
    {
        foreach (['INSERT', 'UPDATE'] as $event) {
            $this->rejectWhen('attachments', $event, 'attachment_has_only_one_owner',
                '(CASE WHEN NEW.request_id IS NULL THEN 0 ELSE 1 END + CASE WHEN NEW.revision_id IS NULL THEN 0 ELSE 1 END + CASE WHEN NEW.message_id IS NULL THEN 0 ELSE 1 END) > 1');
        }

        $this->rejectWhen('attachments', 'UPDATE', 'attachment_owner_is_immutable',
            '(OLD.request_id IS NOT NULL OR OLD.revision_id IS NOT NULL OR OLD.message_id IS NOT NULL) AND (NOT (NEW.request_id __same__ OLD.request_id) OR NOT (NEW.revision_id __same__ OLD.revision_id) OR NOT (NEW.message_id __same__ OLD.message_id))');
        $this->rejectWhen('attachments', 'UPDATE', 'linked_file_content_is_immutable',
            '(OLD.request_id IS NOT NULL OR OLD.revision_id IS NOT NULL OR OLD.message_id IS NOT NULL) AND (NOT (NEW.storage_key __same__ OLD.storage_key) OR NOT (NEW.original_name __same__ OLD.original_name) OR NOT (NEW.detected_media_type __same__ OLD.detected_media_type) OR NOT (NEW.size_bytes __same__ OLD.size_bytes) OR NOT (NEW.content_hash __same__ OLD.content_hash) OR NOT (NEW.uploaded_by __same__ OLD.uploaded_by) OR NOT (NEW.uploaded_at __same__ OLD.uploaded_at))');
        $this->rejectWhen('attachments', 'DELETE', 'linked_file_is_append_only',
            'OLD.request_id IS NOT NULL OR OLD.revision_id IS NOT NULL OR OLD.message_id IS NOT NULL');

        foreach (['INSERT', 'UPDATE'] as $event) {
            $this->rejectWhen('attachments', $event, 'published_request_children_are_immutable',
                'EXISTS (SELECT 1 FROM purchase_requests r WHERE r.id = NEW.request_id AND r.published_at IS NOT NULL)');
            $this->rejectWhen('attachments', $event, 'submitted_revision_children_are_immutable',
                "EXISTS (SELECT 1 FROM quotation_revisions v WHERE v.id = NEW.revision_id AND v.status = 'submitted')");
        }
    }

    private function rejectWhen(string $table, string $event, string $reason, string $condition, ?string $userLock = null): void
    {
        $mysql = DB::getDriverName() === 'mysql';
        $condition = str_replace('__same__', $mysql ? '<=>' : 'IS', $condition);
        $trigger = 'procurement_'.$reason.'_'.mb_strtolower($event);

        if ($mysql) {
            $lock = $userLock === null ? '' : "DECLARE locked_parent BIGINT; SELECT id INTO locked_parent FROM users WHERE id = {$userLock} FOR UPDATE;";
            DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN {$lock} IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$reason}'; END IF; END");
        } else {
            DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, '{$reason}'); END");
        }
    }
};
