<?php

declare(strict_types=1);

namespace Tms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmsUnit extends Model
{
    use HasUuids;

    /** Zulaessige Wortarten. */
    public const array WORD_CLASSES = [
        'noun',
        'verb',
        'adjective',
        'adverb',
        'abbreviation',
        'proper_noun',
    ];

    protected $table = 'tms_units';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'source_lang',
        'source_text',
        'text_hash',
        'domain',
        'char_count',
        'do_not_translate',
        'definition',
        'word_class',
        'preferred_unit_id',
    ];

    protected $casts = [
        'char_count' => 'integer',
        'do_not_translate' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(TmsTranslation::class, 'tms_unit_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(TmsUsage::class, 'tms_unit_id');
    }

    public function mtLogs(): HasMany
    {
        return $this->hasMany(TmsMtLog::class, 'tms_unit_id');
    }

    /**
     * Vorzugsbenennung, falls dieser Begriff ein Synonym ist.
     */
    public function preferredUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'preferred_unit_id');
    }

    /**
     * Begriffe, die auf diesen hier als Vorzugsbenennung verweisen.
     */
    public function synonyms(): HasMany
    {
        return $this->hasMany(self::class, 'preferred_unit_id');
    }

    public function isSynonym(): bool
    {
        return $this->preferred_unit_id !== null;
    }

    /**
     * Braucht dieser Begriff eine maschinelle Uebersetzung?
     *
     * Nein bei "nicht uebersetzen" (der Begriff steht in jeder Sprache gleich)
     * und nein bei Synonymen (sie erben die Uebersetzung der Vorzugsbenennung).
     * Beides spart bares Geld: jeder Begriff kostet sonst einen MT-Aufruf je
     * Zielsprache.
     */
    public function needsTranslation(): bool
    {
        return !$this->do_not_translate && !$this->isSynonym();
    }

    /**
     * Der Text, der im PIM ankommen soll — inklusive der beiden Sonderfaelle.
     *
     * Reihenfolge: eigene Uebersetzung, dann Vorzugsbenennung, dann Quelltext
     * bei "nicht uebersetzen". Sonst null (= noch offen).
     */
    public function effectiveTranslation(string $lang): ?string
    {
        $own = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('target_lang', $lang)
            : $this->translations()->where('target_lang', $lang)->first();

        if ($own) {
            return $own->translation;
        }

        if ($this->do_not_translate) {
            // Bewusst der Quelltext: "IP65" heisst in jeder Sprache "IP65".
            // So gilt der Begriff als erledigt statt dauerhaft als fehlend.
            return $this->source_text;
        }

        if ($this->isSynonym()) {
            // Nur eine Ebene: eine Vorzugsbenennung ist selbst kein Synonym.
            return $this->preferredUnit?->effectiveTranslation($lang);
        }

        return null;
    }
}
