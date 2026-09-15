<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_rounds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->integer('round_number');
            $table->enum('status', ['draft', 'frozen'])->default('draft');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('recommended_candidate_id')->nullable();
            $table->text('recommendation_reason')->nullable();
            $table->text('participation_justification')->nullable();
            $table->json('frozen_snapshot')->nullable();
            $table->string('calculation_version', 30)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->unsignedBigInteger('draft_request_id')->nullable()->storedAs("CASE WHEN status = 'draft' THEN request_id ELSE NULL END")->unique();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['request_id', 'round_number']);
        });

        Schema::create('evaluation_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('round_id')->constrained('evaluation_rounds')->restrictOnDelete();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->enum('disposition', ['pending', 'eligible', 'excluded'])->default('pending');
            $table->text('disposition_reason')->nullable();
            $table->timestampTz('assessed_at')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['round_id', 'quotation_revision_id']);
        });

        Schema::create('line_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('candidate_id')->constrained('evaluation_candidates')->restrictOnDelete();
            $table->foreignId('quotation_line_id')->constrained('quotation_lines')->restrictOnDelete();
            $table->enum('result', ['pending', 'pass', 'fail'])->default('pending');
            $table->text('evidence_note');
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('assessed_at');
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['candidate_id', 'quotation_line_id']);
        });

        Schema::create('criterion_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('candidate_id')->constrained('evaluation_candidates')->restrictOnDelete();
            $table->foreignId('criterion_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->enum('result', ['pending', 'pass', 'fail'])->default('pending');
            $table->text('finding');
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('assessed_at');
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['candidate_id', 'criterion_id']);
        });

        Schema::table('evaluation_rounds', function (Blueprint $table): void {
            $table->foreign('recommended_candidate_id')->references('id')->on('evaluation_candidates')->restrictOnDelete();
        });
    }
};
