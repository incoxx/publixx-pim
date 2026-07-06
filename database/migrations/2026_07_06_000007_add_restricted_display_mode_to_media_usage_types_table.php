<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_usage_types', function (Blueprint $table) {
            // Verhalten, wenn eine Rolle keinen Zugriff auf diesen Medientyp hat (RoleEntityRestriction):
            // "hidden" = Medien dieses Typs werden gar nicht angezeigt, "locked" = sichtbar, aber nicht downloadbar.
            $table->string('restricted_display_mode', 20)->default('locked')->after('allowed_extensions');
        });
    }

    public function down(): void
    {
        Schema::table('media_usage_types', function (Blueprint $table) {
            $table->dropColumn('restricted_display_mode');
        });
    }
};
