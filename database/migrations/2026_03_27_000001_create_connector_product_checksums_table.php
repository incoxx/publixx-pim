<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_product_checksums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->uuid('product_id');
            $table->char('checksum', 64);
            $table->timestamp('synced_at');
            $table->string('external_id')->nullable();

            $table->unique(['connection_id', 'product_id'], 'idx_conn_product');
            $table->index('connection_id', 'idx_connection');

            $table->foreign('connection_id')
                ->references('id')
                ->on('connector_connections')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_product_checksums');
    }
};
