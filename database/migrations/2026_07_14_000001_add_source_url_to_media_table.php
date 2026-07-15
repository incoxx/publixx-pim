<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Quelle bei per URL importierten Medien (z.B. YouTube-Link bei
            // ImportVideoFromUrl) — dient nur der Nachvollziehbarkeit, kein Fremdschlüssel.
            $table->string('source_url', 2000)->nullable()->after('video_thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
