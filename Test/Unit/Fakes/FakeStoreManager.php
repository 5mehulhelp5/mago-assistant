<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A single store view; enough for code that only needs "the current store".
 */
final class FakeStoreManager implements StoreManagerInterface
{
    private StoreInterface $store;

    public function __construct()
    {
        $this->store = (new \ReflectionClass(Store::class))->newInstanceWithoutConstructor();
    }

    public function setIsSingleStoreModeAllowed($value): void
    {
    }

    public function hasSingleStore(): bool
    {
        return true;
    }

    public function isSingleStoreMode(): bool
    {
        return true;
    }

    public function getStore($storeId = null): StoreInterface
    {
        return $this->store;
    }

    public function getStores($withDefault = false, $codeKey = false): array
    {
        return [$this->store];
    }

    public function getWebsite($websiteId = null): never
    {
        throw new \LogicException('FakeStoreManager has no websites.');
    }

    public function getWebsites($withDefault = false, $codeKey = false): array
    {
        return [];
    }

    public function reinitStores(): void
    {
    }

    public function getDefaultStoreView(): StoreInterface
    {
        return $this->store;
    }

    public function getGroup($groupId = null): never
    {
        throw new \LogicException('FakeStoreManager has no store groups.');
    }

    public function getGroups($withDefault = false): array
    {
        return [];
    }

    public function setCurrentStore($store): void
    {
    }
}
