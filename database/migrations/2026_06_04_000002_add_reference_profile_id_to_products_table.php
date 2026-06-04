<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Produkt referenziert genau EIN Referenz-Profil (Scope am Produkt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('reference_profile_id')->nullable()->after('product_type_id')
                ->constrained('product_reference_profiles')->nullOnDelete();
            $table->index('reference_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_profile_id');
        });
    }
};
