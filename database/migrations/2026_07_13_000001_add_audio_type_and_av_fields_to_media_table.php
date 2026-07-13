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
        // 'audio' zum media_type ENUM hinzufügen (mp3 etc., getrennt von 'video')
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media MODIFY COLUMN media_type
                ENUM('image','document','video','audio','other') NOT NULL DEFAULT 'other'");
        }

        // Felder für die asynchrone ffmpeg/ffprobe-Verarbeitung von Audio/Video-Assets
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('height');
            $table->enum('av_processing_status', ['pending', 'processing', 'ready', 'error'])
                ->nullable()->after('duration_seconds')->index();
            $table->text('av_error_message')->nullable()->after('av_processing_status');
            $table->string('video_thumbnail_path', 500)->nullable()->after('av_error_message');
        });
    }

    public function down(): void
    {
        // Bestehende 'audio'-Zeilen vor dem Enum-Revert umhängen, sonst schlägt der
        // nachfolgende ALTER TABLE fehl bzw. würde Daten stillschweigend verstümmeln.
        DB::table('media')->where('media_type', 'audio')->update(['media_type' => 'other']);

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'av_processing_status', 'av_error_message', 'video_thumbnail_path']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media MODIFY COLUMN media_type
                ENUM('image','document','video','other') NOT NULL DEFAULT 'other'");
        }
    }
};
