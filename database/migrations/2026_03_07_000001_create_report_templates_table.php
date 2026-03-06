<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Verknüpfung mit Suchprofil
            $table->char('search_profile_id', 36)->nullable();

            // Template-Definition (Gruppen, Elemente, Stile)
            $table->json('template_json');

            // Ausgabe-Einstellungen
            $table->enum('format', ['docx', 'pdf'])->default('pdf');
            $table->enum('page_orientation', ['portrait', 'landscape'])->default('portrait');
            $table->string('page_size', 20)->default('A4');
            $table->string('language', 5)->default('de');

            // Besitzer
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);

            $table->timestamps();

            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('report_jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('report_template_id', 36);
            $table->char('search_profile_id', 36)->nullable();

            $table->enum('format', ['docx', 'pdf'])->default('pdf');

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

            $table->foreign('report_template_id')->references('id')->on('report_templates')->cascadeOnDelete();
            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
        Schema::dropIfExists('report_templates');
    }
};
