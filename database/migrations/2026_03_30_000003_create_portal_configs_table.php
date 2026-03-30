<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_configs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->longText('html_template');
            $table->char('catalog_template_id', 36)->nullable();
            $table->json('filter_steps');
            $table->json('branding')->nullable();
            $table->json('css_variables')->nullable();
            $table->text('custom_css')->nullable();
            $table->string('default_locale', 10)->default('de');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(true);
            $table->char('user_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('catalog_template_id')
                ->references('id')->on('catalog_templates')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_configs');
    }
};
