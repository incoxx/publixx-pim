<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('column_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->string('context', 50)->default('search');
            $table->json('visible_keys');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'is_shared', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('column_profiles');
    }
};
