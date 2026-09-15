<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('storage_key', 500);
            $table->string('original_name', 255);
            $table->string('detected_media_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('content_hash', 64);
            $table->enum('scan_status', ['pending', 'clean', 'rejected'])->default('pending');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('uploaded_at');
            $table->timestampTz('scanned_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['storage_key']);
        });

        Schema::create('request_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['attachment_id']);
        });

        Schema::create('revision_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['attachment_id']);
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('message_id')->constrained('clarification_messages')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['attachment_id']);
        });
    }
};
