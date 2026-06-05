<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_templates', function (Blueprint $table) {
            // Steuert ob das Template im MCP-Endpoint (Claude Connector) sichtbar ist.
            // Unabhängig von is_active: ein Template kann aktiv aber MCP-versteckt sein.
            $table->boolean('is_mcp_enabled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('api_templates', function (Blueprint $table) {
            $table->dropColumn('is_mcp_enabled');
        });
    }
};
