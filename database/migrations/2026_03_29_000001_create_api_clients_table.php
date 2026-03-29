<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                          // "Tochter Frankreich", "Zentrale DE"
            $table->string('client_id', 64)->unique();       // Öffentlicher Identifikator
            $table->string('client_secret');                  // bcrypt-gehasht
            $table->json('scopes')->nullable();              // ["products:read","products:write",...]
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('rate_limit')->default(600); // Requests pro Minute
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();     // Optionales Ablaufdatum
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
