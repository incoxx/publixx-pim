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
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->char('created_by', 36)->nullable()->after('user_two_id');
            $table->enum('status', ['open', 'done'])->default('open')->after('last_message_at');
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->char('resolved_by', 36)->nullable()->after('resolved_at');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });

        // Bestandsdaten: Ersteller ist unbekannt (kein Feld vorher vorhanden) -- user_one_id
        // als plausibler Default, damit "Löschen" für Alt-Konversationen nicht ins Leere greift.
        DB::table('messenger_conversations')->whereNull('created_by')->update([
            'created_by' => DB::raw('user_one_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['created_by', 'status', 'resolved_at', 'resolved_by']);
        });
    }
};
