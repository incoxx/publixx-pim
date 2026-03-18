<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('slug')->unique();
            $table->longText('html_template');
            $table->json('css_variables')->nullable();
            $table->json('settings')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_shared')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_templates');
    }
};
