<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugriffssteuerung pro Navigation: öffentlich (anonym auslieferbar) oder
 * nur mit Login. Steuert die öffentliche Site-Auslieferung (PublicSiteController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigations', function (Blueprint $table) {
            // 'public' = anonym abrufbar, 'login' = nur authentifiziert
            $table->string('access_mode', 16)->default('public')->after('theme_json');
        });
    }

    public function down(): void
    {
        Schema::table('navigations', function (Blueprint $table) {
            $table->dropColumn('access_mode');
        });
    }
};
