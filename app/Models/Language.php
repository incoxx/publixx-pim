<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Inhaltssprache des PIM.
 *
 * Quelle der Wahrheit für alles, was übersetzt wird: TMS-Zielsprachen,
 * `ProductAttributeValue.language`, die Schlüssel in `name_json`.
 */
class Language extends Model
{
    use HasFactory, HasUuids;

    /** Cache-Schlüssel für die aufgelösten Codes. */
    private const string CACHE_KEY = 'pim:languages:codes';

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'is_source',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_source' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Jede Änderung an der Sprachliste muss sofort greifen — sonst
        // übersetzt der nächste Ingest noch gegen den alten Stand.
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Quellsprache (Ausgangspunkt jeder Übersetzung).
     */
    public static function sourceCode(): string
    {
        return self::codes()['source'];
    }

    /**
     * Aktive Zielsprachen ohne die Quellsprache.
     *
     * @return string[]
     */
    public static function targetCodes(): array
    {
        return self::codes()['targets'];
    }

    /**
     * Alle aktiven Codes inklusive Quellsprache.
     *
     * @return string[]
     */
    public static function activeCodes(): array
    {
        return array_values(array_unique(array_merge(
            [self::sourceCode()],
            self::targetCodes(),
        )));
    }

    /**
     * @return array{source: string, targets: string[]}
     */
    private static function codes(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            // Fallback auf die Env, solange die Tabelle leer ist (frische
            // Installation, Migration noch nicht gelaufen, Tests ohne Seed).
            if (!static::query()->exists()) {
                return [
                    'source' => 'de',
                    'targets' => array_values(array_filter(array_map(
                        'trim',
                        config('tms.target_languages', ['en', 'fr', 'es', 'it', 'nl']),
                    ))),
                ];
            }

            $source = static::query()->where('is_source', true)->value('technical_name') ?? 'de';

            $targets = static::query()
                ->where('is_active', true)
                ->where('technical_name', '!=', $source)
                ->orderBy('sort_order')
                ->pluck('technical_name')
                ->all();

            return ['source' => $source, 'targets' => $targets];
        });
    }
}
