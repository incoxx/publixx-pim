<?php

declare(strict_types=1);

namespace Tms\Tests\Unit;

use Tms\Tests\TestCase;

/**
 * Spiegelbild von tests/Unit/Services/TmsHashTest.php im PIM.
 *
 * PIM und TMS teilen keinen Code — die Hash-Formel ist auf beiden Seiten
 * getrennt implementiert (ProcessIngestBatchJob, ImportTranslationsController
 * hier; App\Services\Tms\TmsHash dort). Beide Testdateien pinnen bewusst
 * DIESELBEN Literale: laufen sie auseinander, findet resolve() nichts mehr
 * wieder und das Translation Memory ist still entwertet.
 *
 * Die Werte müssen identisch zu denen in TmsHashTest::knownHashes() sein.
 */
class HashContractTest extends TestCase
{
    /**
     * Berechnet den Hash exakt so, wie es die Produktivpfade im TMS tun.
     */
    private function hash(string $lang, string $text): string
    {
        return hash('sha256', $lang . '|' . $text);
    }

    /**
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
            $this->hash($lang, $text),
            'Der TMS-Hash hat sich geändert — bestehende tms_units.text_hash-Werte '
            . 'verwaisen damit. Änderung im PIM nachziehen und neu ingesten.',
        );
    }

    public function test_language_is_part_of_the_hash(): void
    {
        $this->assertNotSame($this->hash('de', 'Service'), $this->hash('en', 'Service'));
    }

    public function test_hash_is_sha256_hex(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->hash('de', 'Gewicht'));
    }
}
