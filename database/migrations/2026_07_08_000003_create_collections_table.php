<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('collection_type_id', 36);
            $table->char('organization_id', 36)->nullable();
            $table->json('organization_snapshot')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('name', 500);
            $table->enum('status', ['draft', 'open', 'frozen', 'sent', 'archived'])->default('draft');
            $table->string('language', 5)->default('de');
            $table->string('currency', 3)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->dateTime('frozen_at')->nullable();
            $table->string('source_channel', 50)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('collection_type_id')->references('id')->on('collection_types')->onDelete('restrict');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('collection_type_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
