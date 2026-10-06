<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Api\InProcess\Guard;

use MagoAssistant\Mago\Service\Api\InProcess\Guard\DesignChangeRefusedException;
use MagoAssistant\Mago\Service\Api\InProcess\Guard\DesignFieldGuard;
use MagoAssistant\Mago\Service\Api\InProcess\ResolvedRoute;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAclAuthorization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DesignFieldGuardTest extends TestCase
{
    private const PRODUCT_REPOSITORY = 'Magento\Catalog\Api\ProductRepositoryInterface';
    private const DESIGN_RESOURCE = 'Magento_Catalog::edit_product_design';

    #[Test]
    public function itRefusesADesignAttributeTheAdminMayNotChange(): void
    {
        $route = $this->productSave(['sku' => 'mug', 'custom_attributes' => [
            ['attribute_code' => 'color', 'value' => '49'],
            ['attribute_code' => 'custom_layout_update_file', 'value' => 'evil.xml'],
        ]]);

        $this->expectException(DesignChangeRefusedException::class);
        $this->expectExceptionMessage('custom_layout_update_file');

        $this->guard()->guard($route, new FakeAclAuthorization([]));
    }

    #[Test]
    public function itRefusesADesignFieldSentAtTheTopLevelInEitherCase(): void
    {
        $this->expectException(DesignChangeRefusedException::class);
        $this->expectExceptionMessage('page_layout');

        $this->guard()->guard($this->productSave(['sku' => 'mug', 'pageLayout' => '1column']), new FakeAclAuthorization([]));
    }

    #[Test]
    public function itLetsAnAdminWithTheDesignPermissionChangeTheDesign(): void
    {
        $route = $this->productSave(['custom_attributes' => [['attribute_code' => 'page_layout', 'value' => '1column']]]);

        $this->guard()->guard($route, new FakeAclAuthorization([self::DESIGN_RESOURCE]));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function itLetsASaveWithoutDesignValuesThrough(): void
    {
        $route = $this->productSave(['sku' => 'mug', 'page_layout' => '', 'custom_attributes' => [
            ['attribute_code' => 'custom_design', 'value' => null],
            ['attribute_code' => 'url_key', 'value' => 'mug'],
        ]]);

        $this->guard()->guard($route, new FakeAclAuthorization([]));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function itOnlyGuardsTheServiceMethodItIsConfiguredFor(): void
    {
        $route = new ResolvedRoute('Magento\Cms\Api\PageRepositoryInterface', 'save', '/V1/cmsPage', ['Magento_Cms::save'], [
            'product' => ['page_layout' => '1column'],
        ]);

        $this->guard()->guard($route, new FakeAclAuthorization([]));

        $this->addToAssertionCount(1);
    }

    private function guard(): DesignFieldGuard
    {
        return new DesignFieldGuard(
            self::PRODUCT_REPOSITORY,
            'save',
            'product',
            self::DESIGN_RESOURCE,
            ['custom_design', 'page_layout', 'custom_layout_update_file']
        );
    }

    /**
     * @param array<string, mixed> $product
     */
    private function productSave(array $product): ResolvedRoute
    {
        return new ResolvedRoute(self::PRODUCT_REPOSITORY, 'save', '/V1/products', ['Magento_Catalog::products'], [
            'product' => $product,
        ]);
    }
}
