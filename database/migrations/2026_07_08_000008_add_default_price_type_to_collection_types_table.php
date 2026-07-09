<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * price_types.technical_name des Preistyps, den Collections dieses Typs beim
     * Rendern anfragen (z.B. 'list_price'). Kein FK -- price_types wird im ganzen
     * Repo per technical_name aufgeloest, nicht per ID-Join (siehe PriceResolver).
     */
    public function up(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->string('default_price_type', 100)->nullable()->after('default_render_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->dropColumn('default_price_type');
        });
    }
};
