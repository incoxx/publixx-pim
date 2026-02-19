<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->enum('status', [
                'uploaded', 'validating', 'validated',
                'executing', 'completed', 'failed',
            ])->default('uploaded');
            $table->json('sheets_found')->nullable();
            $table->json('summary')->nullable();
            $table->json('result')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
