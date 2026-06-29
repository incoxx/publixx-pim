<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigations', function (Blueprint $table) {
            // Website-Theme-Tokens (Farben, Schrift, Radius …) für die Vorschau.
            $table->json('theme_json')->nullable()->after('locale_set');
        });
    }

    public function down(): void
    {
        Schema::table('navigations', function (Blueprint $table) {
            $table->dropColumn('theme_json');
        });
    }
};
