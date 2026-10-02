<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Sales\OrderManager\Document;

use MagoAssistant\Mago\Service\Skills\Sales\OrderManager\Document\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VatNumberTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function storedNumbers(): array
    {
        return [
            'already prefixed' => ['NL123456789B01', 'NL', 'NL123456789B01'],
            'prefixed, other country on the address' => ['DE123456789', 'NL', 'DE123456789'],
            'no prefix, billing country supplies it' => ['123456789B01', 'NL', 'NL123456789B01'],
            'separators and case' => ['nl 1234.56789.b01', 'NL', 'NL123456789B01'],
            'lower-case country on the address' => ['123456789B01', 'nl', 'NL123456789B01'],
            'no prefix and no usable country' => ['123456789B01', '', '123456789B01'],
            'empty' => ['', 'NL', ''],
            'only separators' => ['- . ', 'NL', ''],
        ];
    }

    #[Test]
    #[DataProvider('storedNumbers')]
    public function itNormalisesWhatTheShopStored(string $stored, string $country, string $expected): void
    {
        self::assertSame($expected, (new VatNumber())->withCountryPrefix($stored, $country));
    }
}
