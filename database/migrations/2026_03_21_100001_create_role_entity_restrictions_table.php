<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_entity_restrictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->string('restrictable_type');
            $table->uuid('restrictable_id');
            $table->enum('access_level', ['read', 'write', 'delete'])->default('read');
            $table->timestamps();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->unique(['role_id', 'restrictable_type', 'restrictable_id'], 'role_entity_unique');
            $table->index(['role_id', 'restrictable_type'], 'role_entity_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_entity_restrictions');
    }
};
