<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\MediaFileTypes;
use PHPUnit\Framework\TestCase;

class MediaFileTypesTest extends TestCase
{
    /**
     * @dataProvider mimeTypeProvider
     */
    public function test_classify_mime_type(string $mimeType, string $expected): void
    {
        $this->assertSame($expected, MediaFileTypes::classifyMimeType($mimeType));
    }

    public static function mimeTypeProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg', 'image'],
            'png' => ['image/png', 'image'],
            'mp4' => ['video/mp4', 'video'],
            'mpeg video' => ['video/mpeg', 'video'],
            'mp3' => ['audio/mpeg', 'audio'],
            'pdf' => ['application/pdf', 'document'],
            'msword' => ['application/msword', 'document'],
            'octet-stream' => ['application/octet-stream', 'document'],
            'unknown' => ['text/plain', 'other'],
        ];
    }
}
