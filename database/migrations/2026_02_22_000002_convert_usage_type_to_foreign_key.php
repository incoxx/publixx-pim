<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $defaultTypes = [
        ['technical_name' => 'teaser', 'name_de' => 'Hauptbild', 'name_en' => 'Main Image', 'sort_order' => 0],
        ['technical_name' => 'gallery', 'name_de' => 'Galleriebild', 'name_en' => 'Gallery Image', 'sort_order' => 1],
        ['technical_name' => 'document', 'name_de' => 'Dokument', 'name_en' => 'Document', 'sort_order' => 2],
        ['technical_name' => 'technical_drawing', 'name_de' => 'Technische Zeichnung', 'name_en' => 'Technical Drawing', 'sort_order' => 3],
    ];

    public function up(): void
    {
        // 1. Seed default usage types (if not already present)
        foreach ($this->defaultTypes as $type) {
            $exists = DB::table('media_usage_types')
                ->where('technical_name', $type['technical_name'])
                ->exists();

            if (!$exists) {
                DB::table('media_usage_types')->insert(array_merge($type, [
                    'id' => Str::uuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // 2. Also seed any additional ENUM values that exist in data but not in defaults
        $existingValues = DB::table('product_media_assignments')
            ->select('usage_type')
            ->distinct()
            ->whereNotNull('usage_type')
            ->pluck('usage_type');

        foreach ($existingValues as $value) {
            $exists = DB::table('media_usage_types')
                ->where('technical_name', $value)
                ->exists();

            if (!$exists) {
                DB::table('media_usage_types')->insert([
                    'id' => Str::uuid()->toString(),
                    'technical_name' => $value,
                    'name_de' => $value,
                    'name_en' => $value,
                    'sort_order' => 99,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Add new foreign key column
        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->char('usage_type_id', 36)->nullable()->after('media_id');
        });

        // 4. Map existing ENUM values to new IDs
        $usageTypes = DB::table('media_usage_types')->pluck('id', 'technical_name');

        foreach ($usageTypes as $technicalName => $id) {
            DB::table('product_media_assignments')
                ->where('usage_type', $technicalName)
                ->update(['usage_type_id' => $id]);
        }

        // 5. Drop old ENUM column and add foreign key
        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->dropColumn('usage_type');
        });

        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->foreign('usage_type_id')
                ->references('id')
                ->on('media_usage_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->dropForeign(['usage_type_id']);
        });

        // Re-add ENUM column
        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->enum('usage_type', ['teaser', 'gallery', 'document', 'technical_drawing'])
                ->default('gallery')
                ->after('media_id');
        });

        // Map IDs back to ENUM values
        $usageTypes = DB::table('media_usage_types')->pluck('technical_name', 'id');

        foreach ($usageTypes as $id => $technicalName) {
            if (in_array($technicalName, ['teaser', 'gallery', 'document', 'technical_drawing'])) {
                DB::table('product_media_assignments')
                    ->where('usage_type_id', $id)
                    ->update(['usage_type' => $technicalName]);
            }
        }

        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->dropColumn('usage_type_id');
        });
    }
};
