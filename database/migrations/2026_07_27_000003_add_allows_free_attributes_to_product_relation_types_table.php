<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_relation_types', function (Blueprint $table) {
            // Ob an Beziehungen dieses Typs beliebige ("freie") Attribute über den
            // Default-Attribut-Satz hinaus gepflegt werden dürfen. Default true =
            // bisheriges Verhalten (freier Attribut-Picker immer verfügbar), damit
            // bestehende Beziehungstypen unverändert weiterlaufen.
            $table->boolean('allows_free_attributes')->default(true)->after('is_bidirectional');
        });
    }

    public function down(): void
    {
        Schema::table('product_relation_types', function (Blueprint $table) {
            $table->dropColumn('allows_free_attributes');
        });
    }
};
