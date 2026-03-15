<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_statuses')) {
            Schema::create('workflow_statuses', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('color', 7)->default('#6b7280');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_statuses');
    }
};
