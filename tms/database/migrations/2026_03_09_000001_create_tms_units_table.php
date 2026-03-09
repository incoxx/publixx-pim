<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tms_units', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('source_lang', 5)->default('de');
            $table->text('source_text');
            $table->string('text_hash', 64)->unique();
            $table->string('domain', 50)->nullable()->index();
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tms_units');
    }
};
