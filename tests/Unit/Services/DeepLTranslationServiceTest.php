<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Connectors\DeepL\DeepLTranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepLTranslationServiceTest extends TestCase
{
    private DeepLTranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeepLTranslationService();
    }

    public function test_translate_single_text(): void
    {
        Http::fake([
            '*/translate' => Http::response([
                'translations' => [
                    ['text' => 'Hello World'],
                ],
            ]),
        ]);

        $result = $this->service->translateText(
            apiKey: 'test-key',
            text: 'Hallo Welt',
            sourceLang: 'DE',
            targetLang: 'EN',
        );

        $this->assertEquals('Hello World', $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/translate')
                && $request->header('Authorization')[0] === 'DeepL-Auth-Key test-key'
                && $request['source_lang'] === 'DE'
                && $request['target_lang'] === 'EN';
        });
    }

    public function test_translate_multiple_texts(): void
    {
        Http::fake([
            '*/translate' => Http::response([
                'translations' => [
                    ['text' => 'Hello'],
                    ['text' => 'World'],
                ],
            ]),
        ]);

        $results = $this->service->translateTexts(
            apiKey: 'test-key',
            texts: ['Hallo', 'Welt'],
            sourceLang: 'DE',
            targetLang: 'EN',
        );

        $this->assertCount(2, $results);
        $this->assertEquals('Hello', $results[0]);
        $this->assertEquals('World', $results[1]);
    }

    public function test_get_usage_returns_api_data(): void
    {
        Http::fake([
            '*/usage' => Http::response([
                'character_count' => 12345,
                'character_limit' => 500000,
            ]),
        ]);

        $usage = $this->service->getUsage('test-key');

        $this->assertEquals(12345, $usage['character_count']);
        $this->assertEquals(500000, $usage['character_limit']);
    }

    public function test_translate_uses_custom_base_url(): void
    {
        config(['connectors.deepl.base_url' => 'https://api.deepl.com/v2']);

        Http::fake([
            'api.deepl.com/*' => Http::response([
                'translations' => [['text' => 'Test']],
            ]),
        ]);

        $this->service->translateText('key', 'Test', 'DE', 'EN');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.deepl.com'));
    }
}
