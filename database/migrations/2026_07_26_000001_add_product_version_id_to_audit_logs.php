<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Referenz auf die beim Speichern erzeugte Produkt-Version. Ermöglicht,
            // vom Änderungsprotokoll direkt in die Versions-/Diff-Ansicht zu springen,
            // ohne den vollständigen Snapshot im Audit zu duplizieren.
            $table->char('product_version_id', 36)->nullable()->after('auditable_id');

            $table->foreign('product_version_id')
                ->references('id')->on('product_versions')
                ->nullOnDelete();

            $table->index('product_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['product_version_id']);
            $table->dropIndex(['product_version_id']);
            $table->dropColumn('product_version_id');
        });
    }
};
