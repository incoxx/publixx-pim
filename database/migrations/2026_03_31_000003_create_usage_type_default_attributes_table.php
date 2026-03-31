<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_type_default_attributes', function (Blueprint $table) {
            $table->id();
            $table->char('usage_type_id', 36);
            $table->char('attribute_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('usage_type_id')
                ->references('id')->on('media_usage_types')
                ->onDelete('cascade');
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('cascade');

            $table->unique(['usage_type_id', 'attribute_id'], 'utda_type_attr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_type_default_attributes');
    }
};
