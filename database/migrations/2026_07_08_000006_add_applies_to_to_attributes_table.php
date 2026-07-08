<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additiver Scope: auf welchen Entitaetsebenen ein Attribut waehlbar ist
     * (product / collection / collection_item). Default deckt alle Bestandsattribute ab.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->json('applies_to')->default(json_encode(['product']));
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
