<?php

declare(strict_types=1);

namespace Tms\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Tms\Services\TranslationProviders\ProviderChain;
use Tms\Tests\TestCase;

class ProviderChainTest extends TestCase
{
    private function chain(array $config = []): ProviderChain
    {
        config(array_merge([
            'tms.provider_chain' => ['deepl', 'google'],
            'tms.providers.deepl.api_key' => 'deepl-key',
            'tms.providers.deepl.api_url' => 'https://api-free.deepl.com/v2',
            'tms.providers.google.api_key' => 'google-key',
            'tms.providers.claude.api_key' => '',
            'tms.providers.openai.api_key' => '',
        ], $config));

        // Die Kette wird im Konstruktor aus der Config aufgebaut
        return new ProviderChain();
    }

    public function test_chain_is_built_in_configured_order(): void
    {
        $chain = $this->chain(['tms.provider_chain' => ['google', 'deepl']]);

        $this->assertSame(['google', 'deepl'], $chain->availableProviders());
    }

    public function test_unknown_provider_names_are_ignored(): void
    {
        $chain = $this->chain(['tms.provider_chain' => ['deepl', 'does-not-exist', 'google']]);

        $this->assertSame(['deepl', 'google'], $chain->availableProviders());
    }

    public function test_provider_names_are_trimmed(): void
    {
        // TMS_PROVIDER_CHAIN wird per explode(',') zerlegt — " google" kommt vor
        $chain = $this->chain(['tms.provider_chain' => ['deepl', ' google']]);

        $this->assertSame(['deepl', 'google'], $chain->availableProviders());
    }

    public function test_openai_is_a_valid_chain_member(): void
    {
        // Regression: der Provider war waehlbar, aber 'openai' fehlte im
        // provider-ENUM der Tabelle tms_translations.
        $chain = $this->chain([
            'tms.provider_chain' => ['openai'],
            'tms.providers.openai.api_key' => 'openai-key',
        ]);

        $this->assertSame(['openai'], $chain->availableProviders());
    }

    public function test_first_provider_wins(): void
    {
        Http::fake([
            '*deepl*' => Http::response(['translations' => [['text' => 'Weight']]]),
        ]);

        $result = $this->chain()->translate('Gewicht', 'de', 'en');

        $this->assertNotNull($result);
        $this->assertSame('Weight', $result->text);
        $this->assertSame('deepl', $result->provider);
    }

    public function test_falls_back_to_next_provider_on_error(): void
    {
        Http::fake([
            '*deepl*' => Http::response('rate limited', 429),
            '*googleapis*' => Http::response([
                'data' => ['translations' => [['translatedText' => 'Weight']]],
            ]),
        ]);

        $result = $this->chain()->translate('Gewicht', 'de', 'en');

        $this->assertNotNull($result);
        $this->assertSame('google', $result->provider);
    }

    public function test_returns_null_when_every_provider_fails(): void
    {
        Http::fake([
            '*deepl*' => Http::response('boom', 500),
            '*googleapis*' => Http::response('boom', 500),
        ]);

        $this->assertNull($this->chain()->translate('Gewicht', 'de', 'en'));
    }

    public function test_provider_without_api_key_is_skipped(): void
    {
        // DeepL ohne Key meldet supports() === false und darf keinen Call absetzen
        Http::fake([
            '*googleapis*' => Http::response([
                'data' => ['translations' => [['translatedText' => 'Weight']]],
            ]),
        ]);

        $result = $this->chain(['tms.providers.deepl.api_key' => ''])
            ->translate('Gewicht', 'de', 'en');

        $this->assertNotNull($result);
        $this->assertSame('google', $result->provider);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'deepl'));
    }

    public function test_deepl_is_skipped_for_unsupported_language_pair(): void
    {
        // 'xx' steht nicht in DeepLProvider::SUPPORTED_LANGUAGES
        Http::fake([
            '*googleapis*' => Http::response([
                'data' => ['translations' => [['translatedText' => '...']]],
            ]),
        ]);

        $result = $this->chain()->translate('Gewicht', 'de', 'xx');

        $this->assertNotNull($result);
        $this->assertSame('google', $result->provider);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'deepl'));
    }

    public function test_deepl_maps_target_en_to_en_us(): void
    {
        Http::fake(['*deepl*' => Http::response(['translations' => [['text' => 'Weight']]])]);

        $this->chain()->translate('Gewicht', 'de', 'en');

        Http::assertSent(fn ($request) => $request['source_lang'] === 'DE'
            && $request['target_lang'] === 'EN-US');
    }

    public function test_empty_translation_is_treated_as_failure(): void
    {
        Http::fake([
            '*deepl*' => Http::response(['translations' => [['text' => '']]]),
            '*googleapis*' => Http::response([
                'data' => ['translations' => [['translatedText' => 'Weight']]],
            ]),
        ]);

        $result = $this->chain()->translate('Gewicht', 'de', 'en');

        $this->assertNotNull($result);
        $this->assertSame('google', $result->provider);
    }
}
