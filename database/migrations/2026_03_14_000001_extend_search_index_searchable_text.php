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
        Schema::table('products_search_index', function (Blueprint $table) {
            $table->text('searchable_text')->nullable()->after('phonetic_name_de');
            $table->text('media_text')->nullable()->after('searchable_text');
            $table->text('phonetic_text')->nullable()->after('media_text');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE products_search_index ADD FULLTEXT idx_ft_searchable (searchable_text, media_text)');
        }
    }

    public function down(): void
    {
        Schema::table('products_search_index', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropIndex('idx_ft_searchable');
            }
            $table->dropColumn(['searchable_text', 'media_text', 'phonetic_text']);
        });
    }
};
