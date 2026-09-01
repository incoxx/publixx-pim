<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzt die Suchprofile um die Filter, die die Profisuche zwar anbietet, beim
 * Speichern eines Profils aber bisher verworfen hat: Produkttyp, Hersteller und
 * Tags. Ein gespeichertes Profil lieferte dadurch beim Laden mehr Treffer als
 * beim Speichern — ohne sichtbaren Hinweis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_profiles', function (Blueprint $table) {
            $table->json('product_type_ids')->nullable()->after('category_ids');
            $table->json('manufacturer_ids')->nullable()->after('product_type_ids');
            $table->json('tag_ids')->nullable()->after('manufacturer_ids');
            // any = mindestens einer der Tags, all = alle
            $table->string('tag_match', 3)->default('any')->after('tag_ids');
        });
    }

    public function down(): void
    {
        Schema::table('search_profiles', function (Blueprint $table) {
            $table->dropColumn(['product_type_ids', 'manufacturer_ids', 'tag_ids', 'tag_match']);
        });
    }
};
