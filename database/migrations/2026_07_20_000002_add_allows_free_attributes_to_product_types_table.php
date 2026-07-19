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
        Schema::table('product_types', function (Blueprint $table) {
            $table->boolean('allows_free_attributes')->default(false)->after('has_dynamic_cluster');
        });

        // Bisher implizierte has_dynamic_cluster auch das Verhalten "freier
        // Attribut-Picker statt Hierarchie-Attribute" (siehe isVirtualProduct
        // in ProductDetailView.vue). Das ist jetzt ein eigenständiges Flag —
        // bestehende Cluster-Produkttypen behalten ihr bisheriges Verhalten.
        DB::table('product_types')
            ->where('has_dynamic_cluster', true)
            ->update(['allows_free_attributes' => true]);
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->dropColumn('allows_free_attributes');
        });
    }
};
