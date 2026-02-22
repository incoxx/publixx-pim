<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->text('description_de')->nullable();
            $table->text('description_en')->nullable();
            $table->enum('data_type', [
                'String', 'Number', 'Float', 'Date', 'Flag',
                'Selection', 'Dictionary', 'Collection', 'Composite',
            ]);
            $table->char('attribute_type_id', 36)->nullable();
            $table->char('value_list_id', 36)->nullable();
            $table->char('unit_group_id', 36)->nullable();
            $table->char('default_unit_id', 36)->nullable();
            $table->char('comparison_operator_group_id', 36)->nullable();
            $table->boolean('is_translatable')->default(false);
            $table->boolean('is_multipliable')->default(false);
            $table->integer('max_multiplied')->nullable();
            $table->integer('max_pre_decimal')->nullable();
            $table->integer('max_post_decimal')->nullable();
            $table->integer('max_characters')->nullable();
            $table->boolean('is_searchable')->default(true);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_country_specific')->default(false);
            $table->boolean('is_inheritable')->default(true);
            $table->char('parent_attribute_id', 36)->nullable();
            $table->integer('position')->nullable();
            $table->string('source_system', 50)->nullable();
            $table->string('source_attribute_name', 255)->nullable();
            $table->string('source_attribute_key', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('attribute_type_id')->references('id')->on('attribute_types')->onDelete('set null');
            $table->foreign('value_list_id')->references('id')->on('value_lists')->onDelete('set null');
            $table->foreign('unit_group_id')->references('id')->on('unit_groups')->onDelete('set null');
            $table->foreign('default_unit_id')->references('id')->on('units')->onDelete('set null');
            $table->foreign('comparison_operator_group_id')->references('id')->on('comparison_operator_groups')->onDelete('set null');
            $table->foreign('parent_attribute_id')->references('id')->on('attributes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
