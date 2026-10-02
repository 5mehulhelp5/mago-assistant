<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Navigation;

use MagoAssistant\Mago\Service\Skills\Navigation\AdminNavigator;
use MagoAssistant\Mago\Service\Skills\Navigation\PageRegistry;
use MagoAssistant\Mago\Service\Url\AdminRouteAcl;
use MagoAssistant\Mago\Service\Url\EntityRouteMap;
use MagoAssistant\Mago\Service\Url\SecureAdminUrl;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminNavigatorTest extends TestCase
{
    /**
     * Which resource each entity type resolves to is Magento's answer, not this tool's - checked
     * against the real route config in Test/Integration. Here the resolver is stubbed, so what is
     * under test is whether direct-link mode gates on it at all.
     */
    private function navigator(?string $resource = 'Magento_Sales::actions_view'): AdminNavigator
    {
        $secureAdminUrl = $this->createMock(SecureAdminUrl::class);
        $secureAdminUrl->method('getUrl')->willReturn('https://example.test/admin/x/key/abc');

        $adminRouteAcl = $this->createMock(AdminRouteAcl::class);
        $adminRouteAcl->method('forRoute')->willReturn($resource);

        return new AdminNavigator(new PageRegistry(), $secureAdminUrl, new EntityRouteMap($adminRouteAcl));
    }

    #[Test]
    public function aDirectLinkIsGatedByTheEntitysOwnAdminResource(): void
    {
        self::assertSame(
            'Magento_Sales::actions_view',
            $this->navigator()->getMagentoAcl(['entity_type' => 'order', 'entity_id' => 42])
        );
    }

    #[Test]
    public function everyEntityTypeOfferedInTheSchemaIsGated(): void
    {
        $navigator = $this->navigator();
        $offered = $navigator->getParameterSchema()['properties']['entity_type']['enum'];

        self::assertNotEmpty($offered);
        foreach ($offered as $entityType) {
            self::assertNotSame(
                '',
                $navigator->getMagentoAcl(['entity_type' => $entityType, 'entity_id' => 42]),
                sprintf('entity_type "%s" is offered to the model but gated by nothing', $entityType)
            );
        }
    }

    /**
     * A route Magento has no controller for leaves nothing to check.
     */
    #[Test]
    public function anEntityTypeWhoseRouteResolvesToNothingDeclaresNoResource(): void
    {
        self::assertSame(
            '',
            $this->navigator(null)->getMagentoAcl(['entity_type' => 'order', 'entity_id' => 42])
        );
    }

    /**
     * Search mode names the standard admin pages this module ships a registry of and carries no
     * entity to gate on, so it stays on the chat-level read grant alone.
     */
    #[Test]
    public function searchModeDeclaresNoEntityResource(): void
    {
        self::assertSame('', $this->navigator()->getMagentoAcl(['query' => 'orders']));
    }

    /**
     * ToolVerifier asks every tool for its resource without input too, which must not resolve to
     * a resource nobody holds.
     */
    #[Test]
    public function noInputDeclaresNoResource(): void
    {
        self::assertSame('', $this->navigator()->getMagentoAcl());
    }

    /**
     * An entity_type without an id is not direct-link mode: execute() falls through to search.
     */
    #[Test]
    public function anEntityTypeWithoutAnIdIsNotADirectLink(): void
    {
        self::assertSame('', $this->navigator()->getMagentoAcl(['entity_type' => 'order']));
    }

    /**
     * execute() rejects an unknown entity type, so there is no link to gate; returning a resource
     * id that does not exist would deny every admin but the ones with full access.
     */
    #[Test]
    public function anUnknownEntityTypeDeclaresNoResource(): void
    {
        self::assertSame(
            '',
            $this->navigator()->getMagentoAcl(['entity_type' => 'parcel', 'entity_id' => 42])
        );
    }
}
