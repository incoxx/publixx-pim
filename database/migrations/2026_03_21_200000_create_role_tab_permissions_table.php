<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_tab_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->string('tab_key');
            $table->enum('access_level', ['hidden', 'read', 'write'])->default('write');
            $table->timestamps();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->unique(['role_id', 'tab_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_tab_permissions');
    }
};
