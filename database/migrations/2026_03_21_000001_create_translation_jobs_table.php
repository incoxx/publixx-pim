<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('source_language', 5)->default('de');
            $table->string('target_language', 5);
            $table->string('scope')->default('products'); // products, system, mixed
            $table->string('status')->default('draft'); // draft, pending, in_progress, completed, failed, cancelled
            $table->json('filters')->nullable();
            $table->json('attribute_ids')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('translated_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_jobs');
    }
};
