<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Api\InProcess\Guard;

use MagoAssistant\Mago\Service\Api\InProcess\Guard\DesignChangeRefusedException;
use MagoAssistant\Mago\Service\Api\InProcess\Guard\DesignFieldGuard;
use MagoAssistant\Mago\Service\Api\InProcess\ResolvedRoute;
use MagoAssistant\Mago\Service\Api\InProcess\ServiceDispatcher;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAclAuthorization;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The design guards as etc/di.xml configures them. They stand in for Magento's webapi_rest-only
 * ProductAuthorization and PageAclPlugin, so a typo in their configuration would let any admin change a
 * page or product design through the chat. The field lists are the ones Magento's own checks cover.
 */
final class ProductionDesignGuardsTest extends TestCase
{
    private const DI_XML = __DIR__ . '/../../../../../../etc/di.xml';
    private const PRODUCT_GUARD = 'MagoAssistant\Mago\Service\Api\InProcess\Guard\ProductDesignGuard';
    private const CMS_PAGE_GUARD = 'MagoAssistant\Mago\Service\Api\InProcess\Guard\CmsPageDesignGuard';
    private const PRODUCT_REPOSITORY = 'Magento\Catalog\Api\ProductRepositoryInterface';
    private const PAGE_REPOSITORY = 'Magento\Cms\Api\PageRepositoryInterface';
    private const PRODUCT_DESIGN_RESOURCE = 'Magento_Catalog::edit_product_design';
    private const PAGE_DESIGN_RESOURCE = 'Magento_Cms::save_design';

    #[Test]
    public function itRegistersBothDesignGuardsWithTheDispatcher(): void
    {
        $guards = $this->xpath()->query(
            '/config/type[@name="' . ServiceDispatcher::class . '"]/arguments/argument[@name="guards"]/item'
        );

        $guardTypes = array_map(
            static fn (\DOMNode $item): string => trim((string)$item->textContent),
            iterator_to_array($guards === false ? [] : $guards)
        );

        self::assertContains(self::PRODUCT_GUARD, $guardTypes);
        self::assertContains(self::CMS_PAGE_GUARD, $guardTypes);
    }

    #[Test]
    #[DataProvider('cmsPageDesignFields')]
    public function itRefusesACmsPageDesignFieldToAnAdminWithoutTheSaveDesignPermission(string $field): void
    {
        $this->expectException(DesignChangeRefusedException::class);
        $this->expectExceptionMessage($field);

        $this->guard(self::CMS_PAGE_GUARD)->guard(
            $this->pageSave([$field => 'evil']),
            new FakeAclAuthorization(['Magento_Cms::save', 'Magento_Cms::page'])
        );
    }

    #[Test]
    public function itRefusesACmsPageLayoutUpdateSentOverAPutInCamelCase(): void
    {
        $this->expectException(DesignChangeRefusedException::class);

        $this->guard(self::CMS_PAGE_GUARD)->guard(
            $this->pageSave(['id' => 7, 'customLayoutUpdateXml' => '<block/>']),
            new FakeAclAuthorization(['Magento_Cms::save'])
        );
    }

    #[Test]
    public function itLetsAnAdminWithTheSaveDesignPermissionChangeACmsPageDesign(): void
    {
        $route = $this->pageSave(['custom_theme' => '3', 'custom_layout_update_xml' => '<block/>']);

        $this->guard(self::CMS_PAGE_GUARD)->guard($route, new FakeAclAuthorization([self::PAGE_DESIGN_RESOURCE]));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function itLetsACmsPageSaveWithoutDesignFieldsThrough(): void
    {
        $route = $this->pageSave(['title' => 'About us', 'content' => '<p>Hi</p>']);

        $this->guard(self::CMS_PAGE_GUARD)->guard($route, new FakeAclAuthorization([]));

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('productDesignFields')]
    public function itRefusesAProductDesignAttributeToAnAdminWithoutTheEditDesignPermission(string $field): void
    {
        $this->expectException(DesignChangeRefusedException::class);
        $this->expectExceptionMessage($field);

        $this->guard(self::PRODUCT_GUARD)->guard(
            $this->productSave(['sku' => 'mug', 'custom_attributes' => [['attribute_code' => $field, 'value' => 'evil']]]),
            new FakeAclAuthorization(['Magento_Catalog::products'])
        );
    }

    #[Test]
    public function itLetsAnAdminWithTheEditDesignPermissionChangeAProductDesign(): void
    {
        $route = $this->productSave(['custom_attributes' => [['attribute_code' => 'page_layout', 'value' => '1column']]]);

        $this->guard(self::PRODUCT_GUARD)->guard($route, new FakeAclAuthorization([self::PRODUCT_DESIGN_RESOURCE]));

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cmsPageDesignFields(): array
    {
        return self::named([
            'page_layout',
            'layout_update_xml',
            'custom_theme',
            'custom_layout_update_xml',
            'custom_theme_from',
            'custom_theme_to',
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function productDesignFields(): array
    {
        return self::named([
            'custom_design',
            'page_layout',
            'options_container',
            'custom_layout_update',
            'custom_design_from',
            'custom_design_to',
            'custom_layout_update_file',
        ]);
    }

    /**
     * @param string[] $fields
     * @return array<string, array{string}>
     */
    private static function named(array $fields): array
    {
        return array_combine($fields, array_map(static fn (string $field): array => [$field], $fields));
    }

    private function guard(string $virtualType): DesignFieldGuard
    {
        $arguments = $this->getVirtualTypeArguments($virtualType);

        return new DesignFieldGuard(
            $arguments['serviceClass'],
            $arguments['serviceMethod'],
            $arguments['entityKey'],
            $arguments['aclResource'],
            $arguments['designFields']
        );
    }

    /**
     * @return array{serviceClass: string, serviceMethod: string, entityKey: string, aclResource: string, designFields: string[]}
     */
    private function getVirtualTypeArguments(string $virtualType): array
    {
        $xpath = $this->xpath();
        $base = '/config/virtualType[@name="' . $virtualType . '"][@type="' . DesignFieldGuard::class . '"]/arguments';
        $argument = fn (string $name): string => trim((string)$xpath->evaluate('string(' . $base . '/argument[@name="' . $name . '"])'));
        $designFields = $xpath->query($base . '/argument[@name="designFields"]/item');

        return [
            'serviceClass' => $argument('serviceClass'),
            'serviceMethod' => $argument('serviceMethod'),
            'entityKey' => $argument('entityKey'),
            'aclResource' => $argument('aclResource'),
            'designFields' => array_map(
                static fn (\DOMNode $item): string => trim((string)$item->textContent),
                iterator_to_array($designFields === false ? [] : $designFields)
            ),
        ];
    }

    private function xpath(): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->load(self::DI_XML);

        return new \DOMXPath($document);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageSave(array $page): ResolvedRoute
    {
        return new ResolvedRoute(self::PAGE_REPOSITORY, 'save', '/V1/cmsPage', ['Magento_Cms::page'], ['page' => $page]);
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
