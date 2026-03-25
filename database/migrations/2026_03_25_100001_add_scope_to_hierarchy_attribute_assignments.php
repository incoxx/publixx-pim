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
            $table->string('scope', 20)->default('node')->after('sort_order');
            // 'node' = Attribut wird am Hierarchie-Knoten gepflegt
            // 'relationship' = Attribut wird auf der Kante Produkt↔Knoten gepflegt
            // 'both' = Attribut ist an beiden Stellen pflegbar
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_attribute_assignments', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
