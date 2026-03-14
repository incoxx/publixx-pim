<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('product_id', 36);
            $table->string('title', 255);
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->char('assigned_to', 36)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('assigned_to');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_tasks');
    }
};
