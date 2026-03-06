<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Verknüpfung mit Export-/Suchprofil
            $table->char('export_profile_id', 36)->nullable();
            $table->char('search_profile_id', 36)->nullable();

            // Format: json, excel, csv, xml
            $table->enum('format', ['json', 'excel', 'csv', 'xml'])->default('json');

            // Sektionen die exportiert werden (null = alle)
            $table->json('sections')->nullable();

            // Filter (JSON) — status, product_type, hierarchy_path, skus, etc.
            $table->json('filters')->nullable();

            // Zeitplanung
            $table->string('cron_expression', 100)->nullable();
            $table->boolean('is_active')->default(true);

            // Ausführungsstatus
            $table->enum('last_status', ['pending', 'running', 'completed', 'failed'])->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->float('last_duration_seconds')->nullable();
            $table->string('last_output_path', 500)->nullable();
            $table->json('last_result')->nullable();
            $table->text('last_error')->nullable();

            // Besitzer
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);

            $table->timestamps();

            $table->foreign('export_profile_id')->references('id')->on('export_profiles')->nullOnDelete();
            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
