<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_classifications', function (Blueprint $table) {
            $table->string('jira_issue_key', 50)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('error_classifications', function (Blueprint $table) {
            $table->dropColumn('jira_issue_key');
        });
    }
};
