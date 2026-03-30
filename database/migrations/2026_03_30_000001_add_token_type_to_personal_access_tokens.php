<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('token_type')->default('session')->after('name'); // 'session' oder 'api_key'
            $table->string('description')->nullable()->after('token_type');  // z.B. "ERP Integration"
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['token_type', 'description']);
        });
    }
};
