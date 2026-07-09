<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphe Werte-Tabelle fuer freie Metadaten an Collections UND Positionen.
     * Spiegelt product_attribute_values ohne die Vererbungs-Spalten (Vererbung gilt hier nicht).
     *
     * Bekannter Trade-off: owner_id kann keine echte FK-Constraint tragen (zwei moegliche
     * Zieltabellen). Cascade-Delete beim Loeschen einer Collection/CollectionItem erfolgt
     * ueber Model-Events (deleting), nicht ueber DB-FK -- abweichend von allen anderen
     * Werte-Tabellen im Repo (product_attribute_values, media_attribute_values, ...), die
     * jeweils eine eigene FK'd Owner-Spalte je Tabelle nutzen.
     */
    public function up(): void
    {
        Schema::create('collection_attribute_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->enum('owner_type', ['collection', 'collection_item']);
            $table->char('owner_id', 36);
            $table->char('attribute_id', 36);
            $table->text('value_string')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_flag')->nullable();
            $table->char('value_selection_id', 36)->nullable();
            $table->char('unit_id', 36)->nullable();
            $table->string('language', 5)->nullable();
            $table->integer('multiplied_index')->default(0);
            $table->timestamps();

            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('value_selection_id')->references('id')->on('value_list_entries')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');

            $table->unique(
                ['owner_type', 'owner_id', 'attribute_id', 'language', 'multiplied_index'],
                'cav_owner_attr_lang_idx_unique'
            );
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_attribute_values');
    }
};
