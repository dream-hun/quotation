<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 3);
            $table->string('name', 80);
            $table->integer('minor_digits');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['code']);
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 200);
            $table->string('timezone', 80);
            $table->char('default_currency_code', 3);
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreign('default_currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('role_code', ['buyer', 'approver', 'auditor', 'administrator']);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'role_code']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('legal_name', 200);
            $table->string('registration_number', 100)->nullable();
            $table->string('contact_email', 254);
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
        });

        Schema::create('supplier_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unique(['user_id']);
        });
    }
};
