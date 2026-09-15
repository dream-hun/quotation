<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'uuid')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->unique();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('normalized_email', 254)->storedAs('lower(trim(email))')->unique();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->unsignedInteger('lock_version')->default(1);
        });

        DB::table('users')->whereNull('uuid')->select('id')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid7()]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }
};
