<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContentType;
use Illuminate\Database\Seeder;

/**
 * Standard-Seitentypen für strukturierten Content.
 * allowed_section_types / default_sections referenzieren technical_names
 * aus dem SectionTypeSeeder.
 */
class ContentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $allText = ['headline', 'subline', 'teaser', 'text', 'image', 'gallery', 'video', 'download', 'link', 'cta-banner', 'columns', 'spacer'];
        $withCommerce = array_merge($allText, ['product-list', 'product-teaser', 'product-gallery', 'category-teaser', 'category-grid']);
        // Kampagnen-/Landingpage (z. B. WM-Aktionsseite): alles inkl. Countdown & Karussell
        $campaign = array_merge($withCommerce, ['countdown', 'promo-carousel']);

        $types = [
            [
                'technical_name' => 'campaign-page',
                'name_de' => 'Kampagnenseite',
                'name_en' => 'Campaign Page',
                'icon' => 'Megaphone',
                'color' => '#E11D48',
                'layout_hint' => 'landing',
                'allowed_section_types' => $campaign,
                'default_sections' => [
                    ['section_type' => 'cta-banner'],
                    ['section_type' => 'countdown'],
                    ['section_type' => 'category-grid'],
                    ['section_type' => 'product-gallery'],
                    ['section_type' => 'promo-carousel'],
                ],
                'sort_order' => 5,
            ],
            [
                'technical_name' => 'teaser-page',
                'name_de' => 'Teaserseite',
                'name_en' => 'Teaser Page',
                'icon' => 'LayoutTemplate',
                'color' => '#3B82F6',
                'layout_hint' => 'landing',
                'allowed_section_types' => $withCommerce,
                'default_sections' => [
                    ['section_type' => 'headline'],
                    ['section_type' => 'teaser'],
                    ['section_type' => 'product-list'],
                ],
                'sort_order' => 10,
            ],
            [
                'technical_name' => 'chapter-page',
                'name_de' => 'Kapitelseite',
                'name_en' => 'Chapter Page',
                'icon' => 'BookOpen',
                'color' => '#10B981',
                'layout_hint' => 'article',
                'allowed_section_types' => $allText,
                'default_sections' => [
                    ['section_type' => 'headline'],
                    ['section_type' => 'text'],
                ],
                'sort_order' => 20,
            ],
            [
                'technical_name' => 'solution-page',
                'name_de' => 'Lösungsseite',
                'name_en' => 'Solution Page',
                'icon' => 'Lightbulb',
                'color' => '#F59E0B',
                'layout_hint' => 'landing',
                'allowed_section_types' => $withCommerce,
                'default_sections' => [
                    ['section_type' => 'headline'],
                    ['section_type' => 'teaser'],
                    ['section_type' => 'product-list'],
                    ['section_type' => 'cta-banner'],
                ],
                'sort_order' => 30,
            ],
            [
                'technical_name' => 'imprint',
                'name_de' => 'Impressum',
                'name_en' => 'Imprint',
                'icon' => 'Scale',
                'color' => '#6B7280',
                'layout_hint' => 'legal',
                'allowed_section_types' => ['headline', 'subline', 'text'],
                'default_sections' => [
                    ['section_type' => 'headline'],
                    ['section_type' => 'text'],
                ],
                'sort_order' => 40,
            ],
            [
                'technical_name' => 'privacy',
                'name_de' => 'Datenschutz',
                'name_en' => 'Privacy',
                'icon' => 'ShieldCheck',
                'color' => '#6B7280',
                'layout_hint' => 'legal',
                'allowed_section_types' => ['headline', 'subline', 'text'],
                'default_sections' => [
                    ['section_type' => 'headline'],
                    ['section_type' => 'text'],
                ],
                'sort_order' => 50,
            ],
        ];

        foreach ($types as $type) {
            ContentType::updateOrCreate(
                ['technical_name' => $type['technical_name']],
                array_merge($type, ['is_active' => true]),
            );
        }
    }
}
