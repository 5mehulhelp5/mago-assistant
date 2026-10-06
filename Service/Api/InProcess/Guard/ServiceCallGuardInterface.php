<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api\InProcess\Guard;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\LocalizedException;
use MagoAssistant\Mago\Service\Api\InProcess\ResolvedRoute;

/**
 * A check Magento only runs for real REST requests (a webapi_rest plugin) that an in-process call
 * must not skip. It sees the route and its raw input before the service is called.
 */
interface ServiceCallGuardInterface
{
    /**
     * @throws LocalizedException When the call must not go ahead
     */
    public function guard(ResolvedRoute $route, AuthorizationInterface $authorization): void;
}
