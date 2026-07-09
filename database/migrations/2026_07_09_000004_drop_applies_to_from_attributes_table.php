<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rollback von attributes.applies_to (2026_07_08_000006): das Attribut-scoping per
     * product/collection/collection_item hat sich als unnoetige Komplexitaetsebene erwiesen --
     * die einzige tatsaechlich benoetigte Zuordnung ist "welche Attributgruppe (AttributeView)
     * ist einem CollectionType zugewiesen", das Attribut selbst braucht keinen eigenen Scope-Flag.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->json('applies_to')->default(new Expression("('" . json_encode(['product']) . "')"));
        });
    }
};
