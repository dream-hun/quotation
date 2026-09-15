<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('round_id')->constrained('evaluation_rounds')->restrictOnDelete();
            $table->foreignId('assigned_approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'returned', 'rejected'])->default('pending');
            $table->timestampTz('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['round_id']);
            $table->index(['assigned_approver_id', 'status', 'requested_at']);
        });

        Schema::create('awards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('approval_case_id')->constrained('approval_cases')->restrictOnDelete();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->decimal('approved_total', 20, 4);
            $table->char('currency_code', 3);
            $table->foreignId('awarded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('awarded_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['request_id']);
            $table->unique(['approval_case_id']);
        });
    }
};
