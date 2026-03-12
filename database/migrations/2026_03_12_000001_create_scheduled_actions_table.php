<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->string('action_type', 50);
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('pending');
            $table->uuid('product_id')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('payload');
            $table->text('result_message')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->string('color', 20)->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('status');
            $table->index('product_id');
            $table->index('action_type');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_actions');
    }
};
