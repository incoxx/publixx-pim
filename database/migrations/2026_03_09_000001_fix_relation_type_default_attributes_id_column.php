<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('relation_type_default_attributes');

        Schema::create('relation_type_default_attributes', function (Blueprint $table) {
            $table->id();
            $table->char('relation_type_id', 36);
            $table->char('attribute_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('relation_type_id')
                ->references('id')->on('product_relation_types')
                ->onDelete('cascade');
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('cascade');

            $table->unique(['relation_type_id', 'attribute_id'], 'rtda_type_attr_unique');
        });
    }

    public function down(): void
    {
        // Revert to UUID-based id
        Schema::dropIfExists('relation_type_default_attributes');

        Schema::create('relation_type_default_attributes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('relation_type_id', 36);
            $table->char('attribute_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('relation_type_id')
                ->references('id')->on('product_relation_types')
                ->onDelete('cascade');
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('cascade');

            $table->unique(['relation_type_id', 'attribute_id'], 'rtda_type_attr_unique');
        });
    }
};
