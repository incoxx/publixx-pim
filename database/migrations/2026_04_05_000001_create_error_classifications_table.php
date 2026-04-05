<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_classifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Fehler-Fingerprint (für Deduplication)
            $table->string('exception_class', 255);
            $table->string('file', 500);
            $table->unsignedInteger('line');

            // Häufigkeit
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at');

            // Fehler-Details
            $table->text('message');
            $table->text('stack_trace')->nullable();
            $table->string('source', 20)->default('log'); // log | queue

            // KI-Klassifikation
            $table->string('classification', 20)->nullable(); // real_bug | user_error | uncertain
            $table->string('severity', 10)->nullable();       // critical | high | medium | low
            $table->string('ai_title', 255)->nullable();
            $table->text('ai_description')->nullable();
            $table->text('ai_hint')->nullable();
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->timestamp('classified_at')->nullable();

            // Workflow-Status
            $table->string('status', 20)->default('new'); // new | reviewed | forwarded | resolved
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Deduplication: gleiche Exception + Datei + Zeile = ein Eintrag
            $table->unique(['exception_class', 'file', 'line'], 'uq_error_fingerprint');

            // Indizes für häufige Filter
            $table->index('severity');
            $table->index('classification');
            $table->index('status');
            $table->index('last_seen_at');

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_classifications');
    }
};
