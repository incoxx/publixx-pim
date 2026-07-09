<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hierarchy_attribute_assignments', function (Blueprint $table) {
            $table->boolean('is_facet')->default(false)->after('scope');
            // Facetten-Reihenfolge nutzt das bestehende sort_order-Feld (gefiltert auf is_facet=true).
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_attribute_assignments', function (Blueprint $table) {
            $table->dropColumn('is_facet');
        });
    }
};
