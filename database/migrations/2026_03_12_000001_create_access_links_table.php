<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->string('name');
            $table->uuid('role_id');
            $table->uuid('created_by');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_links');
    }
};
