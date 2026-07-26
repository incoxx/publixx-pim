<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Das Snapshot-Format wurde auf Version 2 umgestellt (vollständiger Snapshot
     * inkl. Preise/Medien/Beziehungen/Hierarchie-Zuordnungen) und ist bewusst
     * NICHT abwärtskompatibel. Bestehende Versionen im alten Format werden daher
     * entfernt; ab hier entstehen ausschließlich vollständige Snapshots.
     *
     * Der FK audit_logs.product_version_id ist ON DELETE SET NULL – vorhandene
     * Journal-Einträge bleiben erhalten, verlieren nur ihren Versions-Link.
     */
    public function up(): void
    {
        // DELETE (nicht TRUNCATE), damit der FK-SET-NULL auf audit_logs greift.
        DB::table('product_versions')->delete();
    }

    public function down(): void
    {
        // Gelöschte Verlaufsdaten lassen sich nicht wiederherstellen.
    }
};
