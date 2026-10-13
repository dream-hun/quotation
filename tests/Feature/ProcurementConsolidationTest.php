<?php

declare(strict_types=1);

use App\Concerns\ProcurementAccess;
use App\Models\Attachment;
use App\Models\ClarificationMessage;
use App\Models\PurchaseRequest;
use App\Models\QuotationRevision;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('consolidation preserves existing ownership and supplier assignments while removing four tables', function (): void {
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.consolidation_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('consolidation_upgrade');

    try {
        $migrationPath = 'database/migrations/2026_10_13_095657_consolidate_attachment_ownership_and_supplier_membership.php';
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')) ?: [], fn (string $path): bool => basename($path) < basename($migrationPath)));
        Artisan::call('migrate', ['--database' => 'consolidation_upgrade', '--path' => $paths, '--realpath' => true, '--force' => true]);
        $tableCount = count(Schema::getTableListing());
        $company = Supplier::factory()->create();
        $assigner = User::factory()->create();
        $active = User::factory()->create();
        $inactive = User::factory()->create();
        $assignedAt = now()->subMonth()->startOfSecond();
        foreach ([$active, $inactive] as $user) {
            DB::table('supplier_members')->insert([
                'uuid' => (string) Str::uuid7(), 'user_id' => $user->id, 'supplier_id' => $company->id,
                'status' => $user->is($active) ? 'active' : 'inactive', 'assigned_by' => $assigner->id,
                'created_at' => $assignedAt, 'updated_at' => $assignedAt,
            ]);
        }

        $request = PurchaseRequest::factory()->create();
        $revision = QuotationRevision::factory()->create();
        $message = ClarificationMessage::factory()->create();
        $owners = ['request' => $request, 'revision' => $revision, 'message' => $message];
        $files = [];
        foreach ($owners as $type => $owner) {
            $file = Attachment::factory()->create();
            DB::table($type.'_attachments')->insert([
                'uuid' => (string) Str::uuid7(), 'attachment_id' => $file->id, $type.'_id' => $owner->id,
            ]);
            $files[$type] = $file;
        }
        $request->update(['published_at' => now(), 'status' => 'open']);
        $unlinked = Attachment::factory()->create();
        $userCount = User::query()->count();

        Artisan::call('migrate', ['--database' => 'consolidation_upgrade', '--path' => $migrationPath, '--force' => true]);

        expect(count(Schema::getTableListing()))->toBe($tableCount - 4);
        foreach (['supplier_members', 'request_attachments', 'revision_attachments', 'message_attachments'] as $table) {
            expect(Schema::hasTable($table))->toBeFalse();
        }
        expect(User::query()->count())->toBe($userCount);
        expect(Attachment::query()->count())->toBe(4);
        foreach ($files as $type => $file) {
            $saved = $file->refresh();
            expect($saved->uuid)->toBe($file->uuid);
            expect($saved->storage_key)->toBe($file->storage_key);
            expect($saved->content_hash)->toBe($file->content_hash);
            expect($saved->getAttribute($type.'_id'))->toBe($owners[$type]->id);
        }
        expect($unlinked->refresh()->request_id)->toBeNull();
        expect($active->refresh()->supplier_assigned_by)->toBe($assigner->id);
        expect($active->refresh()->supplier_assigned_at?->equalTo($assignedAt))->toBeTrue();
        expect($inactive->refresh()->supplier_membership_status)->toBe('inactive');
        expect(ProcurementAccess::supplierId($active->refresh()))->toBe($company->id);
        expect(ProcurementAccess::supplierId($inactive->refresh()))->toBeNull();
        expect(fn () => $active->assignRole('buyer'))->toThrow(QueryException::class);
        expect(fn () => $files['request']->update(['storage_key' => 'changed.pdf']))->toThrow(QueryException::class);
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('consolidation_upgrade');
        resolve(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
});

test('owned attachments cannot be moved detached or deleted', function (string $operation): void {
    $file = Attachment::factory()->create(['request_id' => PurchaseRequest::factory()]);

    expect(fn () => match ($operation) {
        'move' => $file->update(['request_id' => PurchaseRequest::factory()->create()->id]),
        'detach' => $file->update(['request_id' => null]),
        'delete' => $file->delete(),
        default => throw new InvalidArgumentException('Unknown attachment operation.'),
    })->toThrow(QueryException::class);
})->with(['move', 'detach', 'delete']);

test('published requests reject new attachments', function (): void {
    $request = PurchaseRequest::factory()->create(['published_at' => now(), 'status' => 'open']);

    expect(fn () => Attachment::factory()->create(['request_id' => $request->id]))
        ->toThrow(QueryException::class, 'published_request_children_are_immutable');
});

test('supplier access reflects reassignment and deactivation without reloading the user', function (): void {
    $user = User::factory()->supplierRepresentative()->create()->refresh();
    $next = Supplier::factory()->create();
    User::query()->whereKey($user->id)->update(['supplier_id' => $next->id]);

    expect(ProcurementAccess::supplierId($user))->toBe($next->id);

    User::query()->whereKey($user->id)->update(['supplier_membership_status' => 'inactive']);

    expect(ProcurementAccess::supplierId($user))->toBeNull();
});

test('administrators can move accounts between internal roles and supplier assignments', function (): void {
    $admin = User::factory()->create()->refresh();
    $admin->assignRole('administrator');
    $user = User::factory()->create()->refresh();
    $user->assignRole('buyer');
    $company = Supplier::factory()->create();
    $input = ['type' => 'user', 'uuid' => $user->uuid, 'name' => $user->name, 'email' => $user->email, 'status' => 'active'];

    $this->actingAs($admin)->post(route('administration.store'), $input + [
        'lock_version' => $user->lock_version, 'supplier_uuid' => $company->uuid, 'roles' => [],
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->supplier_id)->toBe($company->id);
    expect($user->supplier_assigned_by)->toBe($admin->id);
    expect($user->roles()->exists())->toBeFalse();
    expect(ProcurementAccess::supplierId($user))->toBe($company->id);

    $this->post(route('administration.store'), $input + [
        'lock_version' => $user->lock_version, 'roles' => ['buyer'],
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->supplier_id)->toBeNull();
    expect($user->supplier_membership_status)->toBeNull();
    expect($user->supplier_assigned_by)->toBeNull();
    expect($user->supplier_assigned_at)->toBeNull();
    expect($user->hasRole('buyer'))->toBeTrue();
});

test('request and private message uploads retain their owner and download visibility', function (string $parentType): void {
    Illuminate\Support\Facades\Storage::fake('local');
    $buyer = User::factory()->create()->refresh();
    $buyer->assignRole('buyer');
    $request = PurchaseRequest::factory()->create(['created_by' => $buyer->id]);
    $message = null;
    if ($parentType === 'message') {
        $request->update(['status' => 'open', 'published_at' => now()]);
        $message = ClarificationMessage::factory()->create([
            'author_id' => $buyer->id,
            'thread_id' => App\Models\ClarificationThread::factory()->create(['request_id' => $request->id])->id,
        ]);
    }

    $this->actingAs($buyer)->post(route('files.store', $request), [
        'parent_type' => $parentType, 'parent_uuid' => $message?->uuid,
        'file' => Illuminate\Http\UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\nEvidence\n%%EOF"),
    ])->assertSessionHasNoErrors();

    $file = Attachment::query()->sole();
    expect($file->request_id)->toBe($parentType === 'request' ? $request->id : null);
    expect($file->message_id)->toBe($message?->id);
    $this->get(route('files.download', $file))->assertDownload('evidence.pdf');
    $outsider = User::factory()->supplierRepresentative()->create()->refresh();
    $this->actingAs($outsider)->get(route('files.download', $file))->assertNotFound();
})->with(['request', 'message']);
