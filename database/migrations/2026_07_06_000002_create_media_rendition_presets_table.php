<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Konfigurierbare Ausgabeformate für die Rendition-Pipeline (Print/Web/Mobile/Social).
     * Analog zu media_usage_types: Admin-verwaltete Vorlagen, keine Hardcoded-Presets im Code.
     */
    public function up(): void
    {
        Schema::create('media_rendition_presets', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();

            $table->string('channel', 20); // print, web, mobile, social
            $table->string('format', 10); // jpeg, png, webp, tiff
            $table->string('colorspace', 10)->default('rgb'); // rgb, cmyk, gray
            $table->string('fit', 10)->default('contain'); // contain, cover

            $table->unsignedInteger('max_width')->nullable();
            $table->unsignedInteger('max_height')->nullable();
            $table->unsignedInteger('dpi')->nullable();
            $table->unsignedTinyInteger('quality')->nullable();
            $table->string('background_color', 7)->nullable(); // #RRGGBB, für Formate ohne Alpha

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('channel');
            $table->index('is_active');
        });

        $this->seedDefaultPresets();
    }

    private function seedDefaultPresets(): void
    {
        $now = now();
        $presets = [
            [
                'technical_name' => 'web-jpeg-rgb',
                'name_de' => 'Web (JPEG, sRGB)',
                'name_en' => 'Web (JPEG, sRGB)',
                'channel' => 'web',
                'format' => 'jpeg',
                'colorspace' => 'rgb',
                'fit' => 'contain',
                'max_width' => 1920,
                'max_height' => 1920,
                'dpi' => 72,
                'quality' => 85,
                'sort_order' => 10,
            ],
            [
                'technical_name' => 'web-webp-rgb',
                'name_de' => 'Web (WebP, sRGB)',
                'name_en' => 'Web (WebP, sRGB)',
                'channel' => 'web',
                'format' => 'webp',
                'colorspace' => 'rgb',
                'fit' => 'contain',
                'max_width' => 1920,
                'max_height' => 1920,
                'dpi' => 72,
                'quality' => 85,
                'sort_order' => 20,
            ],
            [
                'technical_name' => 'mobile-webp-rgb',
                'name_de' => 'Mobile (WebP, sRGB)',
                'name_en' => 'Mobile (WebP, sRGB)',
                'channel' => 'mobile',
                'format' => 'webp',
                'colorspace' => 'rgb',
                'fit' => 'cover',
                'max_width' => 800,
                'max_height' => 800,
                'dpi' => 72,
                'quality' => 80,
                'sort_order' => 30,
            ],
            [
                'technical_name' => 'social-jpeg-rgb',
                'name_de' => 'Social Media (JPEG, sRGB, quadratisch)',
                'name_en' => 'Social Media (JPEG, sRGB, square)',
                'channel' => 'social',
                'format' => 'jpeg',
                'colorspace' => 'rgb',
                'fit' => 'cover',
                'max_width' => 1080,
                'max_height' => 1080,
                'dpi' => 72,
                'quality' => 85,
                'background_color' => '#FFFFFF',
                'sort_order' => 40,
            ],
            [
                'technical_name' => 'print-tiff-cmyk-300',
                'name_de' => 'Print (TIFF, CMYK, 300dpi)',
                'name_en' => 'Print (TIFF, CMYK, 300dpi)',
                'channel' => 'print',
                'format' => 'tiff',
                'colorspace' => 'cmyk',
                'fit' => 'contain',
                'max_width' => null,
                'max_height' => null,
                'dpi' => 300,
                'quality' => null,
                'background_color' => '#FFFFFF',
                'sort_order' => 50,
            ],
        ];

        foreach ($presets as $preset) {
            DB::table('media_rendition_presets')->insert(array_merge($preset, [
                'id' => (string) Str::uuid(),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_rendition_presets');
    }
};
