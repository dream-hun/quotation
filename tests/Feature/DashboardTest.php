<?php

declare(strict_types=1);

use App\Models\EvaluationRound;
use App\Models\PurchaseRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function (): void {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('approaching deadlines exclude expired and distant requests and ignore history filters', function (): void {
    $this->freezeTime();
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $last = PurchaseRequest::factory()->create(['status' => 'open', 'deadline_at' => now()->addDays(7)]);
    $first = PurchaseRequest::factory()->create(['status' => 'open', 'deadline_at' => now()->addSecond()]);
    PurchaseRequest::factory()->create(['status' => 'open', 'deadline_at' => now()]);
    PurchaseRequest::factory()->create(['status' => 'open', 'deadline_at' => now()->addDays(7)->addSecond()]);
    PurchaseRequest::factory()->create(['status' => 'cancelled', 'deadline_at' => now()->addDay()]);

    $this->actingAs($buyer)->get(route('dashboard', ['q' => 'no matching request']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('requests.data', 0)
            ->where('approachingDeadlines.total', 2)->has('approachingDeadlines.data', 2)
            ->where('approachingDeadlines.data.0.uuid', $first->uuid)
            ->where('approachingDeadlines.data.1.uuid', $last->uuid));
});

test('buyer work queue follows draft ownership and evaluation assignment', function (): void {
    $this->freezeTime();
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $draft = PurchaseRequest::factory()->create(['created_by' => $buyer->id]);
    $closed = PurchaseRequest::factory()->create(['created_by' => $buyer->id, 'status' => 'open', 'deadline_at' => now()]);
    $evaluating = PurchaseRequest::factory()->create(['status' => 'evaluating']);
    EvaluationRound::factory()->create(['request_id' => $evaluating->id, 'prepared_by' => $buyer->id]);
    $otherEvaluation = PurchaseRequest::factory()->create(['created_by' => $buyer->id, 'status' => 'evaluating']);
    EvaluationRound::factory()->create(['request_id' => $otherEvaluation->id]);
    PurchaseRequest::factory()->create();
    PurchaseRequest::factory()->create(['created_by' => $buyer->id, 'status' => 'cancelled']);

    $this->actingAs($buyer)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('assignedTasks.total', 3)
            ->has('assignedTasks.data', 3)
            ->where('assignedTasks.data.0.uuid', $draft->uuid)->where('assignedTasks.data.0.action', 'Complete request')
            ->where('assignedTasks.data.1.uuid', $closed->uuid)->where('assignedTasks.data.1.action', 'Start evaluation')
            ->where('assignedTasks.data.2.uuid', $evaluating->uuid)->where('assignedTasks.data.2.action', 'Continue evaluation'));
});

test('overview lists are bounded while retaining full totals', function (): void {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    PurchaseRequest::factory()->count(9)->create(['created_by' => $buyer->id]);
    PurchaseRequest::factory()->count(9)->create(['status' => 'open', 'deadline_at' => now()->addDay()]);

    $this->actingAs($buyer)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('assignedTasks.total', 9)->has('assignedTasks.data', 8)
            ->where('approachingDeadlines.total', 9)->has('approachingDeadlines.data', 8));
});
