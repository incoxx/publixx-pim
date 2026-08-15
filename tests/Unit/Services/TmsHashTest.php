<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tms\TmsHash;
use Tests\TestCase;

/**
 * Vertragstest für den TMS-Hash.
 *
 * Die erwarteten Werte sind bewusst als Literale festgenagelt und NICHT
 * nachgerechnet: Ein Test, der die Formel selbst noch einmal ausrechnet, würde
 * jede Änderung stillschweigend mitmachen — genau das soll hier auffliegen.
 *
 * Schlägt einer dieser Tests fehl, ist das gesamte bestehende Translation
 * Memory entwertet. Dann muss die Änderung auch im TMS-Service nachgezogen
 * (ProcessIngestBatchJob, ImportTranslationsController) und ein vollständiger
 * Neu-Ingest gefahren werden.
 */
class TmsHashTest extends TestCase
{
    /**
     * Pinnt konkrete Hashes fest. Diese Werte dürfen sich nie ändern.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function knownHashes(): array
    {
        return [
            'Attributname' => [
                'de', 'Gewicht',
                '4832af72c58d6e17409f66409549e956bab7a23855aa27edaa743e64fee9a19a',
            ],
            'leerer Text' => [
                'de', '',
                'faf8acc8c48553fbd9a1419a1c6c9b5b1d06ed9bcf6c6151215fd58200e4aff3',
            ],
            'englische Quelle' => [
                'en', 'Weight',
                'd8f403b484bd1fd70aa3abf225d767e28dacf5aecd088a36718df8a75a69ac99',
            ],
            'mehrere Woerter' => [
                'de', 'Akkubohrer Professional',
                '02c99cc362d32b66207c018daacfec9734ae637e778dee4e754e714d6533f82c',
            ],
            'Umlaut (UTF-8)' => [
                'de', 'Größe',
                'd1440c32e22db6bc7ac8c582f1e6a3e6975eb20b640a8d1701ed8ca72810dfc0',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('knownHashes')]
    public function test_known_hashes_are_stable(string $lang, string $text, string $expected): void
    {
        $this->assertSame(
            $expected,
            TmsHash::for($lang, $text),
            'Der TMS-Hash hat sich geändert — das entwertet das gesamte bestehende '
            . 'Translation Memory. Änderung im TMS-Service nachziehen und neu ingesten.',
        );
    }

    public function test_empty_text_still_carries_the_language_prefix(): void
    {
        // sha256('de|') darf nicht sha256('') sein, sonst kollidieren die Sprachen
        $this->assertNotSame(hash('sha256', ''), TmsHash::for('de', ''));
    }

    public function test_language_is_part_of_the_hash(): void
    {
        $this->assertNotSame(
            TmsHash::for('de', 'Service'),
            TmsHash::for('en', 'Service'),
            'Gleicher Text in anderer Sprache muss einen anderen Hash ergeben.',
        );
    }

    public function test_separator_cannot_be_forged_by_text_content(): void
    {
        // 'de' + '|' + 'x'  vs.  'd' + '|' + 'e|x' — dürfen nicht kollidieren
        $this->assertNotSame(
            TmsHash::for('de', 'x'),
            TmsHash::for('d', 'e|x'),
        );
    }

    public function test_hash_is_sha256_hex(): void
    {
        $hash = TmsHash::for('de', 'Akkubohrer Professional');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_umlauts_and_whitespace_are_significant(): void
    {
        $this->assertNotSame(TmsHash::for('de', 'Größe'), TmsHash::for('de', 'Grosse'));
        $this->assertNotSame(TmsHash::for('de', 'Gewicht'), TmsHash::for('de', 'Gewicht '));
    }
}
