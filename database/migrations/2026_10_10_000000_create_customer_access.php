<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->timestamps();
        });
        DB::statement('alter table users add constraint users_email_canonical check (email = lower(btrim(email)))');
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('phone', 40);
            $table->timestamps();
        });
        Schema::create('customer_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->primary(['user_id', 'customer_id']);
        });
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('customer_user');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('users');
    }
};
