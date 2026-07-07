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
            $table->char('media_language_id', 36)->nullable()->after('language');
            $table->char('media_country_id', 36)->nullable()->after('media_language_id');

            $table->foreign('media_language_id')->references('id')->on('media_languages')->nullOnDelete();
            $table->foreign('media_country_id')->references('id')->on('media_countries')->nullOnDelete();

            $table->index('media_language_id');
            $table->index('media_country_id');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['media_language_id']);
            $table->dropForeign(['media_country_id']);
            $table->dropColumn(['media_language_id', 'media_country_id']);
        });
    }
};
