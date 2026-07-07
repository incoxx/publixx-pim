<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Land" ist bewusst ein freies Nachschlagewerk statt einer ISO-Länderliste:
        // je nach Vertriebsstruktur werden hier echte Länder ODER Regionen gepflegt
        // (z.B. "DACH", "Benelux", "DE").
        Schema::create('media_countries', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de');
            $table->string('name_en')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_countries');
    }
};
