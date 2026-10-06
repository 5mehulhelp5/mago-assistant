<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api\InProcess\Guard;

use Magento\Framework\AuthorizationInterface;
use MagoAssistant\Mago\Service\Api\InProcess\ResolvedRoute;

/**
 * Refuses design fields (layout, theme, layout updates) in a save unless the admin's role allows
 * design changes. Over REST, Magento's ProductAuthorization and PageAclPlugin (webapi_rest only) do this;
 * in-process in adminhtml nothing would. The check is on what the call sends: a design field that is
 * sent with a value needs the resource, whether or not it matches what is saved.
 */
class DesignFieldGuard implements ServiceCallGuardInterface
{
    private const CUSTOM_ATTRIBUTES = 'custom_attributes';
    private const ATTRIBUTE_CODE = 'attribute_code';
    private const VALUE = 'value';

    /**
     * @param string $serviceClass Repository interface whose save is guarded
     * @param string $serviceMethod
     * @param string $entityKey Body key the entity is sent under, e.g. "product"
     * @param string $aclResource Resource that allows design changes
     * @param string[] $designFields
     */
    public function __construct(
        private readonly string $serviceClass,
        private readonly string $serviceMethod,
        private readonly string $entityKey,
        private readonly string $aclResource,
        private readonly array $designFields
    ) {
    }

    public function guard(ResolvedRoute $route, AuthorizationInterface $authorization): void
    {
        if (!$route->isServiceMethod($this->serviceClass, $this->serviceMethod)) {
            return;
        }

        $sentDesignFields = $this->getSentDesignFields($route->inputData[$this->entityKey] ?? []);
        if ($sentDesignFields === [] || $authorization->isAllowed($this->aclResource)) {
            return;
        }

        throw new DesignChangeRefusedException(__(
            'Not allowed to change the design settings (%1): this needs the %2 permission.',
            implode(', ', $sentDesignFields),
            $this->aclResource
        ));
    }

    /**
     * @return string[]
     */
    private function getSentDesignFields(mixed $entity): array
    {
        if (!is_array($entity)) {
            return [];
        }

        $values = $this->getFieldValues($entity);

        return array_values(array_filter(
            $this->designFields,
            fn (string $field): bool => $this->hasValue($values[$field] ?? null)
        ));
    }

    /**
     * @param array<array-key, mixed> $entity
     * @return array<array-key, mixed>
     */
    private function getFieldValues(array $entity): array
    {
        $entity = $this->toSnakeCaseKeys($entity);
        $customAttributes = array_filter(
            is_array($entity[self::CUSTOM_ATTRIBUTES] ?? null) ? $entity[self::CUSTOM_ATTRIBUTES] : [],
            static fn (mixed $attribute): bool => is_array($attribute) && isset($attribute[self::ATTRIBUTE_CODE])
        );

        return array_column($customAttributes, self::VALUE, self::ATTRIBUTE_CODE) + $entity;
    }

    /**
     * REST accepts "pageLayout" as well as "page_layout"; both must be caught.
     *
     * @param array<array-key, mixed> $entity
     * @return array<array-key, mixed>
     */
    private function toSnakeCaseKeys(array $entity): array
    {
        $keys = array_map(
            static fn (int|string $key): string => strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', (string)$key)),
            array_keys($entity)
        );

        return array_combine($keys, array_values($entity));
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
