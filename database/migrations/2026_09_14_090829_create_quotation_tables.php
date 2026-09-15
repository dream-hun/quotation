<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invitation_id')->constrained('invitations')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('withdrawal_reason')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['invitation_id']);
        });

        Schema::create('quotation_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->integer('revision_number');
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->string('supplier_reference', 100)->nullable();
            $table->string('supplier_name_snapshot', 200)->nullable();
            $table->string('supplier_registration_snapshot', 100)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('delivery_days')->nullable();
            $table->text('payment_terms')->nullable();
            $table->decimal('advance_percent', 5, 2)->nullable();
            $table->integer('balance_due_days')->nullable();
            $table->decimal('delivery_charge', 20, 4)->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->string('calculation_version', 30)->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->uuid('submission_key')->nullable();
            $table->unsignedBigInteger('draft_quotation_id')->nullable()->storedAs("CASE WHEN status = 'draft' THEN quotation_id ELSE NULL END")->unique();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['quotation_id', 'revision_number']);
            $table->unique(['submission_key']);
            $table->index(['quotation_id', 'status', 'revision_number']);
        });

        Schema::create('quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->foreignId('request_item_id')->constrained('request_items')->restrictOnDelete();
            $table->text('offered_description')->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('discount_amount', 20, 4)->nullable()->default(0);
            $table->decimal('tax_amount', 20, 4)->nullable()->default(0);
            $table->decimal('line_net', 20, 4)->nullable();
            $table->decimal('line_total', 20, 4)->nullable();
            $table->text('supplier_compliance_response')->nullable();
            $table->text('zero_price_reason')->nullable();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['revision_id', 'request_item_id']);
        });
    }
};
