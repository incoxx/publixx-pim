<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorClassification extends Model
{
    use HasUuids;

    protected $table = 'error_classifications';

    protected $fillable = [
        'exception_class',
        'file',
        'line',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'message',
        'stack_trace',
        'source',
        'classification',
        'severity',
        'ai_title',
        'ai_description',
        'ai_hint',
        'ai_confidence',
        'classified_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'line'             => 'integer',
        'occurrence_count' => 'integer',
        'ai_confidence'    => 'float',
        'first_seen_at'    => 'datetime',
        'last_seen_at'     => 'datetime',
        'classified_at'    => 'datetime',
        'reviewed_at'      => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Berechnet den Schweregrad anhand von Häufigkeit und Klassifikation.
     */
    public static function computeSeverity(int $count, ?string $classification): string
    {
        if ($classification !== 'real_bug') {
            return 'low';
        }

        return match (true) {
            $count >= 10 => 'critical',
            $count >= 3  => 'high',
            default      => 'medium',
        };
    }

    /**
     * Gibt den Fingerprint-Schlüssel zurück (für Deduplication).
     */
    public static function fingerprint(string $exceptionClass, string $file, int $line): string
    {
        return md5("{$exceptionClass}|{$file}|{$line}");
    }
}
