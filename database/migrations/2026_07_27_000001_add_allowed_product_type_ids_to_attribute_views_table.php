<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_views', function (Blueprint $table) {
            // Optionale Produkttyp-Einschränkung: leeres/NULL-Array = für alle
            // Produkttypen gültig (Default, kein Breaking Change); gefülltes Array
            // = Sicht (Tab + Attribut-Filter) nur für diese 1..n Produkttypen sichtbar.
            $table->json('allowed_product_type_ids')->nullable()->after('show_as_tab');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_views', function (Blueprint $table) {
            $table->dropColumn('allowed_product_type_ids');
        });
    }
};
