<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_active')->default(false);
            $table->json('payload');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'is_shared']);
            $table->index('is_active');
        });

        // Migrate existing catalog_theme setting into the new table
        $existing = Setting::getPayload('catalog_theme');

        DB::table('website_profiles')->insert([
            'id' => Str::uuid()->toString(),
            'name' => 'Standard',
            'user_id' => null,
            'is_shared' => true,
            'is_active' => true,
            'payload' => json_encode($existing ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Copy active profile payload back to settings before dropping
        $active = DB::table('website_profiles')->where('is_active', true)->first();
        if ($active) {
            Setting::setPayload('catalog_theme', json_decode($active->payload, true));
        }

        Schema::dropIfExists('website_profiles');
    }
};
