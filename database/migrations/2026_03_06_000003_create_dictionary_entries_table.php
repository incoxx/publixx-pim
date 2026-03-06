<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 100)->nullable()->index();
            $table->string('short_text_de', 255);
            $table->string('short_text_en', 255)->nullable();
            $table->text('long_text_de');
            $table->text('long_text_en')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('attribute_dictionary_entry', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('dictionary_entry_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'dictionary_entry_id'], 'attr_dict_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_dictionary_entry');
        Schema::dropIfExists('dictionary_entries');
    }
};
