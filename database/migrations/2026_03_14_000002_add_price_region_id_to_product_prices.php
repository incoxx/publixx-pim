<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add price_region_id column
        Schema::table('product_prices', function (Blueprint $table) {
            $table->char('price_region_id', 36)->nullable()->after('country');
            $table->foreign('price_region_id')
                ->references('id')
                ->on('price_regions')
                ->nullOnDelete();
        });

        // 2. Migrate existing country values to price_regions
        $countries = DB::table('product_prices')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->pluck('country');

        foreach ($countries as $countryCode) {
            $id = Str::uuid()->toString();
            DB::table('price_regions')->insert([
                'id' => $id,
                'code' => $countryCode,
                'name' => $countryCode,
                'type' => 'country',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('product_prices')
                ->where('country', $countryCode)
                ->update(['price_region_id' => $id]);
        }

        // 3. Drop the old country column
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('valid_to');
        });

        // Migrate back: copy price_region code to country
        $regions = DB::table('price_regions')
            ->where('type', 'country')
            ->get(['id', 'code']);

        foreach ($regions as $region) {
            DB::table('product_prices')
                ->where('price_region_id', $region->id)
                ->update(['country' => $region->code]);
        }

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropForeign(['price_region_id']);
            $table->dropColumn('price_region_id');
        });
    }
};
