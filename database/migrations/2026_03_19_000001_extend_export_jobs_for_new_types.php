<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend format enum to include new types (MySQL only — SQLite stores ENUM as TEXT)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE export_jobs MODIFY COLUMN format ENUM('json','excel','csv','xml','offline_catalog','report','pdf_template') NOT NULL DEFAULT 'json'");
        }

        Schema::table('export_jobs', function (Blueprint $table) {
            // Template references for new job types
            $table->char('catalog_template_id', 36)->nullable()->after('search_profile_id');
            $table->char('report_template_id', 36)->nullable()->after('catalog_template_id');
            $table->char('pdf_template_id', 36)->nullable()->after('report_template_id');

            // Output format for report/pdf_template (pdf, docx, indesign)
            $table->string('output_format', 20)->nullable()->after('pdf_template_id');

            $table->foreign('catalog_template_id')->references('id')->on('catalog_templates')->nullOnDelete();
            $table->foreign('report_template_id')->references('id')->on('report_templates')->nullOnDelete();
            $table->foreign('pdf_template_id')->references('id')->on('pdf_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->dropForeign(['catalog_template_id']);
            $table->dropForeign(['report_template_id']);
            $table->dropForeign(['pdf_template_id']);
            $table->dropColumn(['catalog_template_id', 'report_template_id', 'pdf_template_id', 'output_format']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE export_jobs MODIFY COLUMN format ENUM('json','excel','csv','xml') NOT NULL DEFAULT 'json'");
        }
    }
};
