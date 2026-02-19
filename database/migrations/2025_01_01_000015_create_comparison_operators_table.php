<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_operators', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('group_id', 36);
            $table->string('technical_name', 100);
            $table->string('symbol', 20);
            $table->string('description_de', 255)->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('comparison_operator_groups')->onDelete('cascade');
            $table->unique(['group_id', 'technical_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_operators');
    }
};
