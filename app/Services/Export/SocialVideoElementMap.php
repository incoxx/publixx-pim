<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * Zielschema für Social-Media-Produktvideos (Reels/Shorts, 9:16).
 *
 * Die TARGET_FIELDS werden vom MappingResolver befüllt und vom SocialVideoBuilder
 * zu Video-Szenen (Hero, Features, Preis, CTA) zusammengesetzt.
 */
class SocialVideoElementMap
{
    /**
     * Verfügbare Zielfelder für dieses Format.
     */
    public const TARGET_FIELDS = [
        'hero_image',   // Aufmacherbild (Bildtyp)
        'gallery',      // weitere Bilder (Array)
        'headline',     // Produktname / Überschrift
        'subline',      // Kurzbeschreibung
        'feature_1',    // Merkmal 1
        'feature_2',    // Merkmal 2
        'feature_3',    // Merkmal 3
        'price',        // Preis (Dezimalwert)
        'cta',          // Call-to-Action
    ];

    /**
     * Standard-Mapping-Regeln als Vorlage. Quellen folgen den bekannten
     * MappingResolver-Namespaces (media:, attribute:, prices:).
     */
    public static function defaultMappingRules(): array
    {
        return [
            ['source' => 'media:teaser',             'target' => 'hero_image', 'type' => 'media_url'],
            ['source' => 'media:gallery',            'target' => 'gallery',    'type' => 'media_array'],
            ['source' => 'attribute:name',           'target' => 'headline',   'type' => 'text'],
            ['source' => 'attribute:description-short', 'target' => 'subline',  'type' => 'text'],
            ['source' => 'attribute:feature-1',      'target' => 'feature_1',  'type' => 'text'],
            ['source' => 'attribute:feature-2',      'target' => 'feature_2',  'type' => 'text'],
            ['source' => 'attribute:feature-3',      'target' => 'feature_3',  'type' => 'text'],
            ['source' => 'prices:list_price',        'target' => 'price',      'type' => 'price'],
        ];
    }

    /**
     * Defaults für Felder ohne Mapping-Wert.
     */
    public static function fieldDefaults(): array
    {
        return [
            'cta' => 'Jetzt entdecken',
        ];
    }
}
