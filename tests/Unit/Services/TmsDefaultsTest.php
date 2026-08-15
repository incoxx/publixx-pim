<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tms\TmsClient;
use Tests\TestCase;

/**
 * Nagelt die Voreinstellungen des Translation Memory fest.
 *
 * Das TM ist für jede Installation relevant und daher standardmäßig aktiv.
 * Fehlt TMS_ENABLED in der .env — etwa nach einem Update von einer Version,
 * die die Variable noch nicht kannte —, muss es trotzdem laufen.
 */
class TmsDefaultsTest extends TestCase
{
    /** Konfiguration so laden, wie sie ohne jede Env-Variable entstünde. */
    private function freshConfig(array $env = []): array
    {
        foreach (['TMS_ENABLED', 'TMS_BASE_URL', 'TMS_API_KEY', 'TMS_TIMEOUT', 'TMS_TARGET_LANGUAGES'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }

        return require base_path('config/tms.php');
    }

    protected function tearDown(): void
    {
        foreach (['TMS_ENABLED', 'TMS_BASE_URL', 'TMS_API_KEY', 'TMS_TIMEOUT', 'TMS_TARGET_LANGUAGES'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    public function test_tms_is_enabled_when_the_variable_is_absent(): void
    {
        $config = $this->freshConfig();

        $this->assertTrue(
            $config['enabled'],
            'Ohne TMS_ENABLED muss das Translation Memory aktiv sein.',
        );
    }

    public function test_an_explicit_false_still_switches_it_off(): void
    {
        // Wer es ausdruecklich abschaltet, behaelt das — die Skripte
        // ueberschreiben eine vorhandene Festlegung nicht.
        $this->assertFalse($this->freshConfig(['TMS_ENABLED' => 'false'])['enabled']);
        $this->assertFalse($this->freshConfig(['TMS_ENABLED' => '0'])['enabled']);
    }

    public function test_explicit_true_stays_on(): void
    {
        $this->assertTrue($this->freshConfig(['TMS_ENABLED' => 'true'])['enabled']);
    }

    public function test_default_base_url_points_at_the_local_service(): void
    {
        // setup.sh richtet den Dienst als Apache-VHost auf dem Loopback ein.
        $this->assertSame('http://127.0.0.1:8001/api', $this->freshConfig()['base_url']);
    }

    public function test_client_reflects_the_default(): void
    {
        config(['tms.enabled' => true, 'tms.base_url' => 'http://tms.test/api']);

        $this->assertTrue((new TmsClient())->isEnabled());
    }

    public function test_target_languages_are_trimmed(): void
    {
        // Rueckfallebene — massgeblich ist die Tabelle `languages`. Handgepflegte
        // .env-Werte kommen aber gerne mit Leerzeichen.
        $config = $this->freshConfig(['TMS_TARGET_LANGUAGES' => 'en, fr ,es']);

        $this->assertSame(['en', 'fr', 'es'], array_values($config['target_languages']));
    }
}
