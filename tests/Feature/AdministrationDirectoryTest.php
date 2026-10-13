<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function administrator(): User
{
    $admin = User::factory()->create()->refresh();
    $admin->assignRole('administrator');

    return $admin;
}

test('administrators filter user accounts by role and search', function (): void {
    $admin = administrator();
    $buyer = User::factory()->create(['name' => 'Beatrice Buyer'])->refresh();
    $buyer->assignRole('buyer');
    User::factory()->create(['name' => 'Aaron Approver'])->refresh()->assignRole('approver');

    $this->actingAs($admin)->get(route('administration.users.index', ['role' => 'buyer', 'q' => 'Beatrice']))
        ->assertInertia(fn (Assert $page): Assert => $page->component('administration/users')
            ->has('users.data', 1)
            ->where('users.data.0.uuid', $buyer->uuid)
            ->where('users.data.0.roles', ['buyer'])
            ->has('approvers', 1));
});

test('administrators list suppliers with active representatives', function (): void {
    $admin = administrator();
    $supplier = Supplier::factory()->create(['legal_name' => 'Northline Scientific']);
    Supplier::factory()->create(['legal_name' => 'Meridian Lab Supply']);
    User::factory()->supplierRepresentative($supplier)->create();

    $this->actingAs($admin)->get(route('administration.suppliers.index', ['q' => 'Northline']))
        ->assertInertia(fn (Assert $page): Assert => $page->component('administration/suppliers')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.uuid', $supplier->uuid)
            ->where('suppliers.data.0.members_count', 1));
});

test('only administrators can open the user and supplier directories', function (string $route): void {
    $buyer = User::factory()->create()->refresh();
    $buyer->assignRole('buyer');

    $this->actingAs($buyer)->get(route($route))->assertForbidden();
})->with(['administration.users.index', 'administration.suppliers.index']);
