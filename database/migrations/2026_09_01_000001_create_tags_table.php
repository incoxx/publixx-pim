<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags (mehrsprachige Stichworte) als eigenständige Stammdaten-Entität, die
 * Produkten und Medien zugeordnet werden können.
 *
 * Mehrsprachigkeit nach docs/architecture/05-i18n.md, Level 1 (System Labels):
 * feste Spalten name_de/name_en für schnelle Queries, weitere Sprachen in
 * name_json — analog zu media_usage_types, value_lists usw.
 *
 * Bewusst eine eigene Entität statt weiterer JSON-Spalten wie media.keywords:
 * nur so sind Tags global pflegbar (CRUD-Dialog), umbenennbar ohne Massenupdate
 * und über beide Objekttypen hinweg identisch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Auto-Increment-PK wie bei usage_type_default_attributes: BelongsToMany::sync()
        // füllt keine UUID-PK auf der Pivot-Zeile.
        Schema::create('product_tag', function (Blueprint $table) {
            $table->id();
            $table->char('product_id', 36);
            $table->char('tag_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();

            $table->unique(['product_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('media_tag', function (Blueprint $table) {
            $table->id();
            $table->char('media_id', 36);
            $table->char('tag_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();

            $table->unique(['media_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tag');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');
    }
};
