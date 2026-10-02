<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Sales\OrderManager\Document;

/**
 * The VAT number as stored on a billing address, made usable by whoever reads it off the order.
 *
 * Shops store it as the customer typed it: with or without the country prefix, with spaces and
 * dots, in any case. A register lookup needs the two-letter member state in front, and the billing
 * address knows which state that is - so every consumer gets the same normalised number from the
 * order document rather than each repairing it on its own.
 */
class VatNumber
{
    /**
     * @param string $vatId   The stored value, possibly empty
     * @param string $country The billing address's ISO country code, possibly empty
     * @return string Upper-cased, without separators, prefixed with the country when the stored
     *                value carries no letters of its own; '' when there is no number
     */
    public function withCountryPrefix(string $vatId, string $country): string
    {
        $number = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $vatId));
        if ($number === '') {
            return '';
        }

        $hasPrefix = preg_match('/^[A-Z]{2}/', $number) === 1;
        $country = strtoupper(trim($country));

        return $hasPrefix || strlen($country) !== 2 ? $number : $country . $number;
    }
}
