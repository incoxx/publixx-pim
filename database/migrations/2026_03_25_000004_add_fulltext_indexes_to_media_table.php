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
        // FULLTEXT-Indexe nur für MySQL (SQLite unterstützt kein FULLTEXT)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->fullText(
                ['file_name', 'title_de', 'title_en'],
                'media_ft_name'
            );
            $table->fullText(
                ['description_de', 'description_en'],
                'media_ft_desc'
            );
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->dropFullText('media_ft_name');
            $table->dropFullText('media_ft_desc');
        });
    }
};
