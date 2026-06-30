<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert products.product_type_ref um den Wert 'virtual'.
 *
 * Ein virtuelles Produkt ("die Klammer") ist ein echtes Produkt, dessen
 * Mitglieder dynamisch aus einem Suchprofil, einer PQL-Abfrage oder einer
 * manuellen Auswahl (Merkliste) aufgelöst werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY COLUMN product_type_ref ENUM('product','variant','virtual') NOT NULL DEFAULT 'product'");
        } else {
            // SQLite (Test-Umgebung): CHECK-Constraint über enum()->change() angleichen
            Schema::table('products', function (Blueprint $table) {
                $table->enum('product_type_ref', ['product', 'variant', 'virtual'])->default('product')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY COLUMN product_type_ref ENUM('product','variant') NOT NULL DEFAULT 'product'");
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('product_type_ref', ['product', 'variant'])->default('product')->change();
            });
        }
    }
};
