<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('request_number', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('delivery_destination')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->integer('currency_precision')->nullable();
            $table->timestampTz('deadline_at')->nullable();
            $table->string('timezone', 80);
            $table->enum('status', ['draft', 'open', 'closed', 'evaluating', 'pending_approval', 'awarded', 'rejected', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cloned_from_id')->nullable()->constrained('purchase_requests')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['request_number']);
            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'status']);
            $table->index(['assigned_approver_id', 'status']);
            $table->index(['status', 'deadline_at']);
        });

        Schema::create('request_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->integer('line_number');
            $table->text('description');
            $table->text('specification');
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 30);
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['request_id', 'line_number']);
        });

        Schema::create('evaluation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->string('name', 160);
            $table->enum('category', ['price', 'delivery', 'payment', 'specification']);
            $table->boolean('mandatory')->default(true);
            $table->text('assessment_rule');
            $table->integer('display_order');
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['request_id', 'name']);
            $table->unique(['request_id', 'display_order']);
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('invited_at');
            $table->timestampTz('first_viewed_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['request_id', 'supplier_id']);
            $table->index(['supplier_id', 'invited_at']);
        });
    }
};
