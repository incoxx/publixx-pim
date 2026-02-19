<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('value_list_entries', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('value_list_id', 36);
            $table->char('parent_entry_id', 36)->nullable();
            $table->string('technical_name', 100);
            $table->string('display_value_de', 255);
            $table->string('display_value_en', 255)->nullable();
            $table->json('display_value_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('value_list_id')->references('id')->on('value_lists')->onDelete('cascade');
            $table->foreign('parent_entry_id')->references('id')->on('value_list_entries')->onDelete('set null');
            $table->unique(['value_list_id', 'technical_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('value_list_entries');
    }
};
