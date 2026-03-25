<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Neue Spalten auf media
        Schema::table('media', function (Blueprint $table) {
            $table->string('original_file_name', 500)->nullable()->after('file_name');
            $table->timestamp('last_uploaded_at')->nullable()->after('usage_purpose');
        });

        // Bestehende Daten: original_file_name = file_name, last_uploaded_at = created_at
        DB::statement('UPDATE media SET original_file_name = file_name, last_uploaded_at = created_at WHERE original_file_name IS NULL');

        // 2. media_revisions Tabelle
        Schema::create('media_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('original_file_name', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignUuid('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replaced_at');
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'revision_number']);
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_revisions');

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['original_file_name', 'last_uploaded_at']);
        });
    }
};
