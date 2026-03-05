<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('import_job_id', 36);
            $table->enum('level', ['info', 'warning', 'error'])->default('info');
            $table->enum('phase', ['upload', 'validation', 'execution']);
            $table->string('sheet', 100)->nullable();
            $table->unsignedInteger('row')->nullable();
            $table->string('column', 100)->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('import_job_id')->references('id')->on('import_jobs')->cascadeOnDelete();
            $table->index(['import_job_id', 'level']);
            $table->index(['import_job_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
