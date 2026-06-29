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
        Schema::create('navigation_nodes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('navigation_id', 36);
            $table->char('parent_node_id', 36)->nullable();
            $table->string('path', 1000);
            $table->integer('depth')->default(0);
            $table->integer('sort_order')->default(0);

            // Anzeige
            $table->string('label_de', 255);
            $table->string('label_en', 255)->nullable();
            $table->json('label_json')->nullable();
            $table->string('slug', 255)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_visible')->default(true); // im Menü sichtbar (vs. nur Sitemap)

            // Ziel (Polymorphie)
            $table->enum('target_type', ['folder', 'content_page', 'product_category', 'product', 'external_url'])
                ->default('folder');
            $table->char('content_page_id', 36)->nullable();
            $table->char('hierarchy_node_id', 36)->nullable(); // für 'product_category' (Mount-Point)
            $table->char('product_id', 36)->nullable();
            $table->string('external_url', 1000)->nullable();

            // Mount-Point-Verhalten (gegen doppelten Kategorienbaum)
            $table->boolean('auto_expand')->default(false);
            $table->integer('expand_depth')->nullable();

            $table->timestamps();

            $table->foreign('navigation_id')->references('id')->on('navigations')->onDelete('cascade');
            $table->foreign('parent_node_id')->references('id')->on('navigation_nodes')->onDelete('cascade');
            $table->foreign('content_page_id')->references('id')->on('content_pages')->onDelete('set null');
            $table->foreign('hierarchy_node_id')->references('id')->on('hierarchy_nodes')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');

            $table->index(['navigation_id', 'parent_node_id']);

            if (DB::getDriverName() === 'mysql') {
                $table->rawIndex('`path`(768)', 'navigation_nodes_path_index');
            } else {
                $table->index('path', 'navigation_nodes_path_index');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_nodes');
    }
};
