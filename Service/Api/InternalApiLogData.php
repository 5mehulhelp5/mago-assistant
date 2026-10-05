<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api;

/**
 * What the debug log records about an internal REST call: metadata only. Request and response
 * bodies carry store data and personal details, and a query string can hold search values such as
 * an email address, so none of them are written, only their sizes.
 */
final class InternalApiLogData
{
    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function forRequest(string $method, string $url, int $adminUserId, ?array $body): array
    {
        return [
            'method' => $method,
            'path' => $this->pathOf($url),
            'admin_user_id' => $adminUserId,
            'body_bytes' => $body === null ? 0 : strlen((string)json_encode($body)),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function forResponse(int $statusCode, string $responseBody, float $durationSeconds): array
    {
        return [
            'status' => $statusCode,
            'body_length' => strlen($responseBody),
            'duration_ms' => (int)round($durationSeconds * 1000),
        ];
    }

    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }
}
