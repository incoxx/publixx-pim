<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tms_mt_log', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('provider', 20);
            $table->string('source_lang', 5);
            $table->string('target_lang', 5);
            $table->unsignedInteger('char_count');
            $table->decimal('cost_estimate', 8, 4)->nullable();
            $table->char('tms_unit_id', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tms_unit_id')->references('id')->on('tms_units')->nullOnDelete();
            $table->index('provider');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tms_mt_log');
    }
};
