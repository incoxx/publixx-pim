<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 64)->index();
            $table->string('severity', 16)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('country', 2)->nullable();
            // Kein Foreign Key: Events sollen erhalten bleiben, auch wenn der Nutzer geloescht wird.
            $table->uuid('user_id')->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('blocked')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
