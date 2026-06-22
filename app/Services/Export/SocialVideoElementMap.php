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
     * Zentrale, konfigurierbare Feld-Definitionen — die einzige Quelle der
     * Wahrheit für GUI-Dropdowns UND Default-Regeln. Nichts ist im Frontend
     * fest verdrahtet: die GUI rendert die Auswahl dynamisch aus dieser Liste.
     *
     * - kind:  steuert, welche Quell-Liste die GUI anbietet (media|attribute|price)
     * - type:  MappingResolver-Typ der erzeugten Regel
     * - default: technischer Name der Standard-Quelle (nur Vorbelegung, überschreibbar)
     *
     * @return list<array{target:string,label:string,kind:string,type:string,default:string}>
     */
    public static function configurableFields(): array
    {
        return [
            ['target' => 'hero_image', 'label' => 'Aufmacherbild',  'kind' => 'media',     'type' => 'media_url',   'default' => 'teaser'],
            ['target' => 'gallery',    'label' => 'Galerie-Bilder', 'kind' => 'media',     'type' => 'media_array', 'default' => 'gallery'],
            ['target' => 'headline',   'label' => 'Überschrift',    'kind' => 'attribute', 'type' => 'text',        'default' => 'name'],
            ['target' => 'subline',    'label' => 'Unterzeile',     'kind' => 'attribute', 'type' => 'text',        'default' => 'description-short'],
            ['target' => 'feature_1',  'label' => 'Feature 1',      'kind' => 'attribute', 'type' => 'text',        'default' => 'feature-1'],
            ['target' => 'feature_2',  'label' => 'Feature 2',      'kind' => 'attribute', 'type' => 'text',        'default' => 'feature-2'],
            ['target' => 'feature_3',  'label' => 'Feature 3',      'kind' => 'attribute', 'type' => 'text',        'default' => 'feature-3'],
            ['target' => 'price',      'label' => 'Preis',          'kind' => 'price',     'type' => 'price',       'default' => 'list_price'],
        ];
    }

    /**
     * Quell-Präfix (MappingResolver-Namespace) für eine Feld-Art.
     */
    public static function prefixForKind(string $kind): string
    {
        return match ($kind) {
            'media' => 'media:',
            'price' => 'prices:',
            default => 'attribute:',
        };
    }

    /**
     * Standard-Mapping-Regeln als Vorlage — abgeleitet aus configurableFields(),
     * damit es keine doppelte Wahrheit gibt.
     */
    public static function defaultMappingRules(): array
    {
        $rules = [];
        foreach (self::configurableFields() as $field) {
            $rules[] = [
                'source' => self::prefixForKind($field['kind']) . $field['default'],
                'target' => $field['target'],
                'type'   => $field['type'],
            ];
        }

        return $rules;
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
