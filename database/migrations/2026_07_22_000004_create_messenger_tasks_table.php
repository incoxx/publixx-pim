<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('message_id', 36)->nullable();
            $table->char('conversation_id', 36)->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->char('product_id', 36)->nullable();
            $table->char('assigned_to', 36);
            $table->char('created_by', 36);
            $table->enum('status', ['open', 'in_progress', 'done'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messenger_messages')->nullOnDelete();
            $table->foreign('conversation_id')->references('id')->on('messenger_conversations')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();

            $table->index('assigned_to');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_tasks');
    }
};
