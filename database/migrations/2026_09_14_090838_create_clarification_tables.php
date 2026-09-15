<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_threads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('quotation_revision_id')->nullable()->constrained('quotation_revisions')->restrictOnDelete();
            $table->enum('visibility', ['private', 'all_invited'])->default('private');
            $table->string('subject', 200);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->boolean('material')->default(false);
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->index(['request_id', 'status', 'material']);
        });

        Schema::create('clarification_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('thread_id')->constrained('clarification_threads')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['thread_id', 'created_at']);
        });
    }
};
