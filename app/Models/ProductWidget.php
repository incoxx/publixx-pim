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
}
