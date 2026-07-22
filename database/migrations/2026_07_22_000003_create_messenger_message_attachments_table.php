<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('message_id', 36);
            // Morph-Alias ueber Relation::morphMap() in AppServiceProvider (z.B. 'product').
            $table->string('attachable_type', 60);
            $table->char('attachable_id', 36);
            // Nur fuer die UI-Gruppierung: 'product' (einzeln angehaengt) oder
            // 'merkliste' (als Teil einer Merkliste-Anhang-Gruppe versendet).
            $table->enum('attached_via', ['product', 'merkliste'])->default('product');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on('messenger_messages')->cascadeOnDelete();

            $table->index(['message_id']);
            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_message_attachments');
    }
};
