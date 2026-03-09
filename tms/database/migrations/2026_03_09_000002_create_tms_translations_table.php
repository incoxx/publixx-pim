<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tms_translations', function (Blueprint $table) {
            $table->char('tms_unit_id', 36);
            $table->string('target_lang', 5);
            $table->text('translation');
            $table->enum('provider', ['deepl', 'google', 'claude', 'human', 'import'])->default('deepl');
            $table->enum('status', ['pending', 'auto', 'reviewed'])->default('pending');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->primary(['tms_unit_id', 'target_lang']);
            $table->foreign('tms_unit_id')->references('id')->on('tms_units')->cascadeOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tms_translations');
    }
};
