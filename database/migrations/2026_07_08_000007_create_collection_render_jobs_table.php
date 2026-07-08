<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spiegelt report_jobs (Ausfuehrungsstatus fuer Collection-PDF/XLSX-Renders),
     * FK auf collections statt report_templates.
     */
    public function up(): void
    {
        Schema::create('collection_render_jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('collection_id', 36);

            $table->enum('format', ['pdf', 'xlsx'])->default('pdf');

            // Ausführungsstatus
            $table->enum('last_status', ['pending', 'running', 'completed', 'failed'])->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->float('last_duration_seconds')->nullable();
            $table->string('last_output_path', 500)->nullable();
            $table->json('last_result')->nullable();
            $table->text('last_error')->nullable();

            // Besitzer
            $table->char('user_id', 36)->nullable();

            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_render_jobs');
    }
};
