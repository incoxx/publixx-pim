<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_usage_types', function (Blueprint $table) {
            // Optionale Produkttyp-Einschränkung: leeres/NULL-Array = für alle
            // Produkttypen gültig (Default, kein Breaking Change); gefülltes Array
            // = Medientyp wird im Produkteditor nur bei diesen 1..n Produkttypen
            // zur Zuordnung angeboten.
            $table->json('allowed_product_type_ids')->nullable()->after('restricted_display_mode');
        });
    }

    public function down(): void
    {
        Schema::table('media_usage_types', function (Blueprint $table) {
            $table->dropColumn('allowed_product_type_ids');
        });
    }
};
