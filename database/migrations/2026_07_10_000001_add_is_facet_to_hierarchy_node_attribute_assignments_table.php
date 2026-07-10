<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->boolean('is_facet')->default(false)->after('attribute_sort');
            // Facetten-Reihenfolge nutzt das bestehende attribute_sort-Feld (gefiltert auf is_facet=true).
            // Gilt aktuell nur für Asset-Hierarchien (hierarchy_type=asset) — für Produkt-Hierarchien
            // steuert weiterhin ausschließlich das theme-weite facet_attribute_ids-Setting die Facetten.
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->dropColumn('is_facet');
        });
    }
};
