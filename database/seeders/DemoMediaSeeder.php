<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\MediaAttributeValue;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoMediaSeeder extends Seeder
{
    /**
     * Farbschemata pro Produkt (Hintergrund-Gradient + Akzentfarbe).
     */
    private const PRODUCT_COLORS = [
        'PD-18V-001' => ['start' => [0, 90, 180], 'end' => [0, 60, 140], 'accent' => [255, 200, 0]],
        'PD-18V-002' => ['start' => [180, 30, 30], 'end' => [140, 20, 20], 'accent' => [255, 255, 255]],
        'HD-18V-001' => ['start' => [0, 90, 180], 'end' => [20, 50, 100], 'accent' => [255, 160, 0]],
        'PD-18V-003' => ['start' => [180, 30, 30], 'end' => [120, 20, 40], 'accent' => [200, 200, 200]],
        'HD-36V-001' => ['start' => [40, 40, 50], 'end' => [20, 20, 30], 'accent' => [0, 200, 255]],
    ];

    /**
     * Bildvarianten pro Produkt: [suffix, breite, höhe, beschreibung_de, beschreibung_en, usage_type].
     */
    private const IMAGE_VARIANTS = [
        ['main', 800, 800, 'Produktbild Frontalansicht', 'Product image front view', 'teaser'],
        ['angle', 800, 800, 'Produktbild Schrägansicht', 'Product image angle view', 'gallery'],
        ['detail', 600, 600, 'Detailansicht Griff', 'Detail view handle', 'gallery'],
    ];

    public function run(): void
    {
        // Storage-Verzeichnis sicherstellen
        Storage::disk('public')->makeDirectory('demo-media');

        $usageTypes = MediaUsageType::all()->keyBy('technical_name');
        $teaserType = $usageTypes['teaser'] ?? null;
        $galleryType = $usageTypes['gallery'] ?? null;

        if (! $teaserType || ! $galleryType) {
            $this->command?->warn('MediaUsageTypes teaser/gallery nicht gefunden — überspringe.');
            return;
        }

        $products = Product::whereIn('sku', array_keys(self::PRODUCT_COLORS))->get()->keyBy('sku');

        if ($products->isEmpty()) {
            $this->command?->warn('Keine Demo-Produkte gefunden — DemoProductSeeder zuerst ausführen.');
            return;
        }

        // Ordnerstruktur der Medien-Hierarchie (DemoHierarchySeeder) für die Ablage der Assets
        $assetHierarchy = Hierarchy::where('technical_name', 'asset_medienstruktur')->first();
        $produktbilderFolder = $assetHierarchy
            ? HierarchyNode::where('hierarchy_id', $assetHierarchy->id)->where('name_de', 'Produktbilder')->first()
            : null;
        $marketingFolder = $assetHierarchy
            ? HierarchyNode::where('hierarchy_id', $assetHierarchy->id)->where('name_de', 'Marketing & Kampagnen')->first()
            : null;

        // Referenz-Attribute, mit denen Assets per Metadaten direkt mit einem
        // Produkt bzw. einem Master-Hierarchie-Knoten verlinkt werden können
        $linkedProductAttr = Attribute::where('technical_name', 'media-linked-product-ref')->first();
        $linkedCategoryAttr = Attribute::where('technical_name', 'media-linked-category-ref')->first();

        foreach ($products as $sku => $product) {
            $colors = self::PRODUCT_COLORS[$sku] ?? self::PRODUCT_COLORS['PD-18V-001'];

            foreach (self::IMAGE_VARIANTS as $sortOrder => [$suffix, $width, $height, $descDe, $descEn, $usageKey]) {
                $fileName = strtolower($sku) . '-' . $suffix . '.png';
                $filePath = 'demo-media/' . $fileName;

                // Bild nur generieren, wenn es noch nicht existiert
                if (! Storage::disk('public')->exists($filePath)) {
                    $imageData = $this->generateProductImage(
                        $width,
                        $height,
                        $product->name,
                        $sku,
                        $suffix,
                        $colors,
                    );
                    Storage::disk('public')->put($filePath, $imageData);
                }

                $fileSize = Storage::disk('public')->size($filePath);
                $folder = $suffix === 'detail' ? $marketingFolder : $produktbilderFolder;

                // Media-Record
                $media = Media::firstOrCreate(
                    ['file_name' => $fileName],
                    [
                        'file_path' => $filePath,
                        'mime_type' => 'image/png',
                        'file_size' => $fileSize,
                        'media_type' => 'image',
                        'title_de' => $product->name . ' — ' . $descDe,
                        'title_en' => $product->name . ' — ' . $descEn,
                        'description_de' => $descDe,
                        'description_en' => $descEn,
                        'alt_text_de' => $product->name,
                        'alt_text_en' => $product->name,
                        'width' => $width,
                        'height' => $height,
                        'asset_folder_id' => $folder?->id,
                    ]
                );

                // Produkt-Zuordnung
                $usageType = $usageTypes[$usageKey] ?? $galleryType;
                ProductMediaAssignment::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'media_id' => $media->id,
                    ],
                    [
                        'usage_type_id' => $usageType->id,
                        'sort_order' => $sortOrder,
                        'is_primary' => $suffix === 'main',
                    ]
                );

                // Metadaten-Relation: das Titelbild wird zusätzlich per Attribut direkt
                // mit dem Produkt und dessen Master-Hierarchie-Knoten verlinkt — unabhängig
                // von der klassischen Produkt-Medien-Zuordnung oben.
                if ($suffix === 'main' && $linkedProductAttr) {
                    MediaAttributeValue::firstOrCreate(
                        ['media_id' => $media->id, 'attribute_id' => $linkedProductAttr->id, 'language' => null, 'multiplied_index' => 0],
                        ['value_string' => $product->id]
                    );
                }
                if ($suffix === 'main' && $linkedCategoryAttr && $product->master_hierarchy_node_id) {
                    MediaAttributeValue::firstOrCreate(
                        ['media_id' => $media->id, 'attribute_id' => $linkedCategoryAttr->id, 'language' => null, 'multiplied_index' => 0],
                        ['value_string' => $product->master_hierarchy_node_id]
                    );
                }
            }

            $this->command?->info("Medien erzeugt für: {$sku} — {$product->name}");
        }
    }

    /**
     * Erzeugt ein Produktbild mit GD: Gradient-Hintergrund, Werkzeug-Silhouette, Produktname und SKU.
     */
    private function generateProductImage(
        int $width,
        int $height,
        string $productName,
        string $sku,
        string $variant,
        array $colors,
    ): string {
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, true);

        // Hintergrund-Gradient (vertikal)
        [$sr, $sg, $sb] = $colors['start'];
        [$er, $eg, $eb] = $colors['end'];
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max($height - 1, 1);
            $r = (int) ($sr + ($er - $sr) * $ratio);
            $g = (int) ($sg + ($eg - $sg) * $ratio);
            $b = (int) ($sb + ($eb - $sb) * $ratio);
            $lineColor = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $lineColor);
        }

        // Akzentfarbe
        [$ar, $ag, $ab] = $colors['accent'];
        $accent = imagecolorallocate($img, $ar, $ag, $ab);
        $white = imagecolorallocate($img, 255, 255, 255);
        $whiteAlpha = imagecolorallocatealpha($img, 255, 255, 255, 80);
        $dark = imagecolorallocate($img, 0, 0, 0);
        $darkAlpha = imagecolorallocatealpha($img, 0, 0, 0, 90);

        // Werkzeug-Silhouette (stilisierter Bohrer)
        $this->drawToolSilhouette($img, $width, $height, $whiteAlpha, $variant);

        // Akzent-Streifen unten
        $barHeight = (int) ($height * 0.12);
        $barY = $height - $barHeight;
        imagefilledrectangle($img, 0, $barY, $width, $height, $darkAlpha);

        // Akzent-Linie
        imagefilledrectangle($img, 0, $barY, $width, $barY + 3, $accent);

        // Produktname (oben)
        $fontSizeName = 5; // GD built-in font (1-5)
        $nameX = 20;
        $nameY = 20;
        imagestring($img, $fontSizeName, $nameX + 1, $nameY + 1, $productName, $dark);
        imagestring($img, $fontSizeName, $nameX, $nameY, $productName, $white);

        // SKU + Variante (im Akzent-Balken)
        $label = strtoupper($sku) . '  |  ' . strtoupper($variant);
        $labelX = 20;
        $labelY = $barY + (int) (($barHeight - 16) / 2) + 5;
        imagestring($img, 4, $labelX, $labelY, $label, $accent);

        // „DEMO" Wasserzeichen (diagonal, mittig)
        $this->drawWatermark($img, $width, $height, $whiteAlpha);

        // Als PNG-String ausgeben
        ob_start();
        imagepng($img, null, 6);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    /**
     * Zeichnet eine stilisierte Werkzeug-Silhouette (Bohrer/Schrauber).
     */
    private function drawToolSilhouette(\GdImage $img, int $width, int $height, int $color, string $variant): void
    {
        $cx = (int) ($width * 0.5);
        $cy = (int) ($height * 0.45);

        switch ($variant) {
            case 'main':
                // Bohrmaschinenkorpus (Seitenansicht)
                // Gehäuse
                $bodyW = (int) ($width * 0.35);
                $bodyH = (int) ($height * 0.18);
                imagefilledrectangle($img, $cx - $bodyW / 2, $cy - $bodyH / 2, $cx + $bodyW / 2, $cy + $bodyH / 2, $color);
                // Griff
                $gripW = (int) ($width * 0.08);
                $gripH = (int) ($height * 0.25);
                imagefilledrectangle($img, $cx - $gripW / 2, $cy + $bodyH / 2, $cx + $gripW / 2, $cy + $bodyH / 2 + $gripH, $color);
                // Bohrfutter
                $chuckW = (int) ($width * 0.15);
                $chuckH = (int) ($height * 0.08);
                imagefilledrectangle($img, $cx + $bodyW / 2, $cy - $chuckH / 2, $cx + $bodyW / 2 + $chuckW, $cy + $chuckH / 2, $color);
                // Bohrer
                $drillLen = (int) ($width * 0.12);
                $drillH = (int) ($height * 0.02);
                imagefilledrectangle($img, $cx + $bodyW / 2 + $chuckW, $cy - $drillH / 2, $cx + $bodyW / 2 + $chuckW + $drillLen, $cy + $drillH / 2, $color);
                break;

            case 'angle':
                // Schrägansicht — leicht gedreht (Parallelogramm)
                $offset = (int) ($width * 0.06);
                $bw = (int) ($width * 0.30);
                $bh = (int) ($height * 0.16);
                $points = [
                    $cx - $bw / 2 + $offset, $cy - $bh / 2,
                    $cx + $bw / 2 + $offset, $cy - $bh / 2,
                    $cx + $bw / 2 - $offset, $cy + $bh / 2,
                    $cx - $bw / 2 - $offset, $cy + $bh / 2,
                ];
                imagefilledpolygon($img, $points, $color);
                // Griff
                $gw = (int) ($width * 0.07);
                $gh = (int) ($height * 0.22);
                imagefilledrectangle($img, $cx - $gw / 2 - $offset / 2, $cy + $bh / 2, $cx + $gw / 2 - $offset / 2, $cy + $bh / 2 + $gh, $color);
                break;

            case 'detail':
                // Detailansicht: Nahaufnahme Bohrfutter (Kreis + Linien)
                $radius = (int) ($width * 0.15);
                imageellipse($img, $cx, $cy, $radius * 2, $radius * 2, $color);
                imageellipse($img, $cx, $cy, (int) ($radius * 1.4), (int) ($radius * 1.4), $color);
                imageellipse($img, $cx, $cy, (int) ($radius * 0.6), (int) ($radius * 0.6), $color);
                // Spannbacken
                for ($angle = 0; $angle < 360; $angle += 120) {
                    $rad = deg2rad($angle);
                    $x1 = $cx + (int) (cos($rad) * $radius * 0.3);
                    $y1 = $cy + (int) (sin($rad) * $radius * 0.3);
                    $x2 = $cx + (int) (cos($rad) * $radius);
                    $y2 = $cy + (int) (sin($rad) * $radius);
                    imageline($img, $x1, $y1, $x2, $y2, $color);
                }
                break;
        }
    }

    /**
     * Zeichnet ein diagonales „DEMO" Wasserzeichen.
     */
    private function drawWatermark(\GdImage $img, int $width, int $height, int $color): void
    {
        $text = 'DEMO';
        $font = 5; // Größte eingebaute Schrift
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $textW = $charW * strlen($text);

        // Mehrfach diagonal wiederholen
        $spacing = 120;
        for ($x = -$height; $x < $width + $height; $x += $spacing) {
            for ($y = 0; $y < $height; $y += $spacing) {
                // GD hat keine Rotation für imagestring — Text horizontal, versetzt anordnen
                $posX = $x + (int) ($y * 0.5);
                $posY = $y;
                if ($posX > -$textW && $posX < $width && $posY > -$charH && $posY < $height) {
                    imagestring($img, $font, $posX, $posY, $text, $color);
                }
            }
        }
    }
}
