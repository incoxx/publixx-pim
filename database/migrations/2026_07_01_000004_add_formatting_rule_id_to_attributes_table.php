<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->char('formatting_rule_id', 36)->nullable()->after('value_list_id');
            $table->foreign('formatting_rule_id')->references('id')->on('attribute_formatting_rules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropForeign(['formatting_rule_id']);
            $table->dropColumn('formatting_rule_id');
        });
    }
};
