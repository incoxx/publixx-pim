<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->string('search_text', 500)->nullable();
            $table->enum('search_mode', ['like', 'soundex', 'regex'])->default('like');
            $table->string('status_filter', 20)->nullable();
            $table->json('category_ids')->nullable();
            $table->json('attribute_filters')->nullable();
            $table->boolean('include_descendants')->default(true);
            $table->string('sort_field', 50)->default('updated_at');
            $table->string('sort_order', 4)->default('desc');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_profiles');
    }
};
