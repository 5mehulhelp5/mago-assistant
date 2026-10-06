<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api\InProcess;

use Magento\AsynchronousOperations\Api\Data\AsyncResponseInterface;
use Magento\AsynchronousOperations\Model\MassSchedule;
use Magento\Framework\Exception\BulkException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\WebapiAsync\Model\Config as AsyncConfig;

/**
 * Queues a call on async.operations.all the way POST /rest/async/V1/... does, without that HTTP
 * request: Magento's own async publisher reads the current request body, which in-process is the chat.
 * The route's ACL is checked for the admin first and the bulk is recorded under that admin user.
 */
class AsyncScheduler
{
    public function __construct(
        private readonly StoreEmulation $storeEmulation,
        private readonly AdminAuthorizationFactory $authorizationFactory,
        private readonly RouteResolver $routeResolver,
        private readonly RouteAuthorizer $routeAuthorizer,
        private readonly ServiceInputProcessor $serviceInputProcessor,
        private readonly AsyncConfig $asyncConfig,
        private readonly MassSchedule $massSchedule,
        private readonly DataObjectProcessor $dataObjectProcessor,
        private readonly OutputNormalizer $outputNormalizer,
        private readonly ErrorMapper $errorMapper
    ) {
    }

    /**
     * @return array<array-key, mixed> bulk_uuid, request_items and errors, as the async REST call answers
     */
    public function schedule(ApiCall $call): array
    {
        try {
            return $this->storeEmulation->run($call, fn (): array => $this->publish($call));
        } catch (\Throwable $throwable) {
            return $this->errorMapper->toError($throwable);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function publish(ApiCall $call): array
    {
        $route = $this->routeResolver->resolve($call);
        $this->routeAuthorizer->assertAllowed($route, $this->authorizationFactory->create($call->adminUserId));

        $arguments = $this->serviceInputProcessor->process(
            $route->serviceClass,
            $route->serviceMethod,
            $route->inputData
        );

        return $this->outputNormalizer->normalize($this->dataObjectProcessor->buildOutputDataArray(
            $this->publishMass($this->asyncConfig->getTopicName($route->routePath, $call->httpMethod), $arguments, $call),
            AsyncResponseInterface::class
        ));
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function publishMass(string $topicName, array $arguments, ApiCall $call): AsyncResponseInterface
    {
        try {
            return $this->massSchedule->publishMass($topicName, [$arguments], null, $call->adminUserId);
        } catch (BulkException $bulkException) {
            return $bulkException->getData();
        }
    }
}
