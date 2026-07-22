<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Kanonisch sortiert (kleinere UUID immer in user_one_id), damit der
            // Unique-Index unabhaengig von der Gespraechsrichtung Duplikate verhindert.
            $table->char('user_one_id', 36);
            $table->char('user_two_id', 36);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('user_one_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_two_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_conversations');
    }
};
