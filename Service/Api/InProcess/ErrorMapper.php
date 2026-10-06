<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api\InProcess;

use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Hands a failed call back in the shape the HTTP client always returned: ['error' => message]. The
 * message is Magento's raw, untranslated text with its placeholders filled in, as REST sends it, so a
 * tool can still recognise a message like "... already exists" whatever the admin's interface locale.
 */
class ErrorMapper
{
    public const PERMISSION_DENIED = 'You do not have permission to access this data';
    public const NOT_FOUND = 'Resource not found';

    public function __construct(
        private readonly ExceptionMaskerInterface $exceptionMasker
    ) {
    }

    /**
     * @return array{error: string}
     */
    public function toError(\Throwable $throwable): array
    {
        return ['error' => $this->getMessage($this->exceptionMasker->mask($this->toException($throwable)))];
    }

    /**
     * A TypeError means the input did not fit the service method, which REST answers with a 400 and
     * the PHP message. Any other Error is a crash, masked like every unexpected exception.
     */
    private function toException(\Throwable $throwable): \Exception
    {
        return match (true) {
            $throwable instanceof \Exception => $throwable,
            $throwable instanceof \TypeError => new WebapiException(new Phrase($throwable->getMessage())),
            default => new \RuntimeException($throwable->getMessage(), (int)$throwable->getCode(), $throwable),
        };
    }

    /**
     * There is no token anymore that could have expired, so a 401 can only mean the admin user's role
     * does not allow the call: it reads as the 403 it is.
     */
    private function getMessage(WebapiException $exception): string
    {
        return match ($exception->getHttpCode()) {
            WebapiException::HTTP_UNAUTHORIZED, WebapiException::HTTP_FORBIDDEN => self::PERMISSION_DENIED,
            WebapiException::HTTP_NOT_FOUND => self::NOT_FOUND,
            default => $this->renderMessage($exception->getRawMessage(), $exception->getDetails()),
        };
    }

    /**
     * Magento hands a web api error back as an unrendered phrase and its arguments, so the message
     * on its own still reads 'The status "%1" is not part of the order status history'. Putting the
     * arguments back is the difference between an admin reading which status was refused and
     * reading a placeholder.
     *
     * @param array<array-key, mixed> $parameters
     */
    private function renderMessage(string $message, array $parameters): string
    {
        $replacements = [];
        $index = 1;
        foreach ($parameters as $key => $value) {
            if (!is_scalar($value) && !$value instanceof Phrase) {
                continue;
            }
            $replacements['%' . (is_string($key) ? $key : $index)] = (string)$value;
            $index++;
        }

        return strtr($message, $replacements);
    }
}
