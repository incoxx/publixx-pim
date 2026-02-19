<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_job_errors', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('import_job_id', 36);
            $table->string('sheet', 100)->nullable();
            $table->integer('row')->nullable();
            $table->string('column', 5)->nullable();
            $table->string('field', 100)->nullable();
            $table->text('value')->nullable();
            $table->text('error');
            $table->text('suggestion')->nullable();
            $table->timestamps();

            $table->foreign('import_job_id')->references('id')->on('import_jobs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_job_errors');
    }
};
