<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Api;

use MagoAssistant\Mago\Service\Api\InternalApiLogData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InternalApiLogDataTest extends TestCase
{
    #[Test]
    public function aRequestIsLoggedByPathAndSizeWithoutItsQueryOrBody(): void
    {
        $entry = (new InternalApiLogData())->forRequest(
            'POST',
            'https://shop.test/rest/default/V1/customers/search?searchCriteria[filter]=jane@example.com',
            7,
            ['customer' => ['email' => 'jane@example.com']]
        );

        self::assertSame(
            [
                'method' => 'POST',
                'path' => '/rest/default/V1/customers/search',
                'admin_user_id' => 7,
                'body_bytes' => strlen('{"customer":{"email":"jane@example.com"}}'),
            ],
            $entry
        );
    }

    #[Test]
    public function aRequestWithoutABodyIsLoggedWithZeroBodyBytes(): void
    {
        $entry = (new InternalApiLogData())->forRequest('GET', 'https://shop.test/rest/V1/orders', 7, null);

        self::assertSame(0, $entry['body_bytes']);
    }

    #[Test]
    public function aFailedResponseIsLoggedByStatusSizeAndDurationWithoutItsBody(): void
    {
        $body = '{"message":"Customer jane@example.com already exists"}';

        $entry = (new InternalApiLogData())->forResponse(400, $body, 0.1234);

        self::assertSame(['status' => 400, 'body_length' => strlen($body), 'duration_ms' => 123], $entry);
    }
}
