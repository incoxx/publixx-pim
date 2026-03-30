<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('usage_purpose');
            $table->string('document_type', 100)->nullable()->after('language');

            $table->index('language');
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['language']);
            $table->dropIndex(['document_type']);
            $table->dropColumn(['language', 'document_type']);
        });
    }
};
