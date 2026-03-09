<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // export_jobs erweitern: Delivery-Konfiguration
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->string('delivery_type', 20)->nullable()->after('cron_expression');
            $table->json('delivery_config')->nullable()->after('delivery_type');
        });

        // Ausführungsprotokoll
        Schema::create('export_job_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('export_job_id', 36);
            $table->enum('status', ['running', 'completed', 'failed']);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->float('duration_seconds')->nullable();
            $table->string('output_path', 500)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('delivery_status', 20)->nullable();
            $table->text('delivery_error')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('export_job_id')
                ->references('id')
                ->on('export_jobs')
                ->cascadeOnDelete();

            $table->index(['export_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_job_logs');

        Schema::table('export_jobs', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'delivery_config']);
        });
    }
};
