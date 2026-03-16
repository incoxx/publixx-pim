<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Verknüpfung mit Suchprofil
            $table->char('search_profile_id', 36)->nullable();

            // Template-Definition (Gruppen, Elemente — identisch zu Berichten)
            $table->json('template_json');

            // Richtung
            $table->enum('direction', ['export', 'import', 'bidirectional'])->default('export');

            // Sprache für Attribut-Auflösung
            $table->string('language', 5)->default('de');

            // Besitzer
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);

            // Stream-Endpoint
            $table->boolean('is_active')->default(true);
            $table->string('slug', 100)->unique()->nullable();
            $table->enum('auth_type', ['bearer', 'api_key', 'none'])->default('api_key');
            $table->string('api_key', 255)->nullable();
            $table->unsignedInteger('rate_limit')->default(60);

            $table->timestamps();

            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('api_template_access_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('api_template_id', 36);
            $table->string('ip_address', 45);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('product_count')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('api_template_id')->references('id')->on('api_templates')->cascadeOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_template_access_logs');
        Schema::dropIfExists('api_templates');
    }
};
