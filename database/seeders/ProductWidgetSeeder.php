<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductWidget;
use Illuminate\Database\Seeder;

/**
 * Standard-Produkt-Widgets (Anzeige-Definitionen für eingebettete Produktblöcke).
 * Quellen nutzen die MappingResolver-Namespaces (attribute:/prices:/media:) bzw.
 * product: für Basisfelder. is_system = true → nicht löschbar.
 */
class ProductWidgetSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->widgets() as $w) {
            ProductWidget::updateOrCreate(
                ['technical_name' => $w['technical_name']],
                array_merge($w, ['is_system' => true, 'is_active' => true]),
            );
        }
    }

    private function widgets(): array
    {
        return [
            [
                'technical_name' => 'card',
                'name_de' => 'Produktkarte',
                'name_en' => 'Product Card',
                'description' => 'Bild, Name und Preis — Standard für Galerien/Listen.',
                'sort_order' => 10,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => 'media:teaser', 'type' => 'media_url'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'price', 'source' => 'prices:list_price', 'type' => 'price'],
                    ],
                    'show_sku' => false,
                    'image_ratio' => '4/3',
                    'cta' => ['enabled' => false],
                ],
            ],
            [
                'technical_name' => 'hero',
                'name_de' => 'Hero (Einzelprodukt)',
                'name_en' => 'Hero',
                'description' => 'Großes Bild, Name, Kurzbeschreibung, Preis und Button.',
                'sort_order' => 20,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => 'media:teaser', 'type' => 'media_url'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'subtitle', 'source' => 'attribute:description-short', 'type' => 'text'],
                        ['role' => 'price', 'source' => 'prices:list_price', 'type' => 'price'],
                    ],
                    'show_sku' => false,
                    'image_ratio' => '16/9',
                    'cta' => ['enabled' => true, 'label' => 'Mehr erfahren'],
                ],
            ],
            [
                'technical_name' => 'list-row',
                'name_de' => 'Listenzeile',
                'name_en' => 'List Row',
                'description' => 'Kleines Bild, Name und Preis nebeneinander.',
                'sort_order' => 30,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => 'media:teaser', 'type' => 'media_url'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'price', 'source' => 'prices:list_price', 'type' => 'price'],
                    ],
                    'show_sku' => true,
                    'image_ratio' => '1/1',
                    'cta' => ['enabled' => false],
                ],
            ],
            [
                'technical_name' => 'spec-tile',
                'name_de' => 'Datenblatt-Kachel',
                'name_en' => 'Spec Tile',
                'description' => 'Name, Preis und ausgewählte technische Attribute.',
                'sort_order' => 40,
                'config' => [
                    'fields' => [
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'price', 'source' => 'prices:list_price', 'type' => 'price'],
                        ['role' => 'attribute', 'source' => 'attribute:weight', 'type' => 'unit_value'],
                        ['role' => 'attribute', 'source' => 'attribute:dimensions', 'type' => 'text'],
                    ],
                    'show_sku' => true,
                    'image_ratio' => '4/3',
                    'cta' => ['enabled' => false],
                ],
            ],
        ];
    }
}
