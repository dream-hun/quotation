<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('purchase_requests')->restrictOnDelete();
            $table->string('event_key', 100);
            $table->string('event_type', 50);
            $table->json('safe_payload');
            $table->timestampTz('read_at')->nullable();
            $table->enum('email_status', ['not_required', 'pending', 'sending', 'sent', 'failed'])->default('not_required');
            $table->integer('email_attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestampTz('email_sent_at')->nullable();
            $table->timestampTz('claimed_until')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['recipient_id', 'event_key']);
            $table->index(['recipient_id', 'read_at', 'created_at']);
            $table->index(['email_status', 'next_attempt_at']);
            $table->index(['email_status', 'claimed_until']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->nullable()->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_label', 160);
            $table->string('action', 80);
            $table->string('target_type', 50);
            $table->uuid('target_id');
            $table->timestampTz('occurred_at');
            $table->uuid('correlation_id');
            $table->json('safe_details');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['request_id', 'occurred_at', 'id']);
            $table->index(['target_type', 'target_id']);
            $table->index(['correlation_id']);
        });
    }
};
