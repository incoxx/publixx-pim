<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tms_usages', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tms_unit_id', 36);
            $table->string('entity_type', 50);
            $table->char('entity_id', 36);
            $table->string('field_name', 50);
            $table->string('entity_label', 255)->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();

            $table->unique(['tms_unit_id', 'entity_type', 'entity_id', 'field_name'], 'tms_usages_unique');
            $table->foreign('tms_unit_id')->references('id')->on('tms_units')->cascadeOnDelete();
            $table->index('entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tms_usages');
    }
};
