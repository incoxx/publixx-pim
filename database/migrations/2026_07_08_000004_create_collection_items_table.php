<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_items', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('collection_id', 36);
            // nullable = Freitext-/Fremdposition erlaubt (kein Produktbezug im PIM)
            $table->char('product_id', 36)->nullable();
            $table->integer('position')->default(0);
            $table->decimal('quantity', 20, 6)->default(1);
            $table->char('unit_id', 36)->nullable();
            // Eingefrorener abgeleiteter Produktzustand (name, description, primary_image,
            // resolved_price, aufgeloeste Attribute) -- siehe SnapshotService (Phase 3).
            $table->json('snapshot')->nullable();
            $table->dateTime('snapshot_at')->nullable();
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->onDelete('cascade');
            // set null statt cascade: eine eingefrorene Position ueberlebt das Loeschen des Produkts.
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');

            $table->index(['collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};
