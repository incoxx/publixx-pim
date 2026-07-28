<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AttributeValueFormatter;
use PHPUnit\Framework\TestCase;

class AttributeValueFormatterTest extends TestCase
{
    /**
     * @dataProvider numberProvider
     */
    public function test_number_trims_trailing_zeros(int|float|string|null $input, ?string $expected): void
    {
        $this->assertSame($expected, AttributeValueFormatter::number($input));
    }

    public static function numberProvider(): array
    {
        return [
            'ganzzahl mit dezimal-cast' => ['82.000000', '82'],
            'dezimalwert' => ['1.800000', '1.8'],
            'halber wert' => ['6.500000', '6.5'],
            'null-wert' => ['0.000000', '0'],
            'grosse ganzzahl' => ['100.000000', '100'],
            'negativ' => ['-5.000000', '-5'],
            'ohne dezimalpunkt' => ['12', '12'],
            'float-typ' => [1.5, '1.5'],
            'int-typ' => [7, '7'],
            'null' => [null, null],
        ];
    }
}
