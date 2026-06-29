<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Produkt-Widget: wiederverwendbare Definition, WELCHE Produktdaten ein
 * eingebetteter Produktblock anzeigt (Bild, Titel, Preis, Attribute, Badge,
 * CTA) und in welcher Rolle. Bewusst getrennt von der Katalog-Vorschau-Config
 * (website_profiles.payload) — gleiche Datenbasis, eigene Konfiguration.
 */
class ProductWidget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description',
        'config',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'config' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Beim Löschen eines Widgets dessen Verweise in Content-Sektionen
        // nachziehen (values_json["…"]["widget"] = technical_name). Sonst
        // zeigt eine Sektion auf ein nicht mehr existentes Widget; ohne
        // Bereinigung bliebe eine tote Referenz zurück.
        static::deleting(function (ProductWidget $widget) {
            $name = $widget->technical_name;

            $sections = ContentSection::query()
                ->where('values_json', 'like', '%"' . $name . '"%')
                ->get();

            foreach ($sections as $section) {
                $values = $section->values_json ?? [];
                $changed = false;
                foreach ($values as $bucket => $fields) {
                    if (is_array($fields) && ($fields['widget'] ?? null) === $name) {
                        unset($values[$bucket]['widget']);
                        $changed = true;
                    }
                }
                if ($changed) {
                    $section->update(['values_json' => $values]);
                }
            }
        });
    }
}
