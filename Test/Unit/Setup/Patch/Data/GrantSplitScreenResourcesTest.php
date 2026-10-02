<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Setup\Patch\Data;

use Magento\Framework\Acl\Data\CacheInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use MagoAssistant\Mago\Setup\Patch\Data\GrantSplitScreenResources;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GrantSplitScreenResourcesTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private CacheInterface&MockObject $aclCache;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->aclCache = $this->createMock(CacheInterface::class);
    }

    #[Test]
    public function aConfigRoleKeepsStatisticsAndSkillsButNotConversations(): void
    {
        $this->connection->method('fetchCol')->willReturn(['4']);
        $this->connection->expects(self::once())->method('insertMultiple')->with(
            'authorization_rule',
            self::callback(static function (array $rows): bool {
                $resources = array_column($rows, 'resource_id');

                return $resources === GrantSplitScreenResources::GRANTED_RESOURCES
                    && !in_array('MagoAssistant_Mago::conversations', $resources, true)
                    && array_unique(array_column($rows, 'role_id')) === [4];
            })
        );
        $this->aclCache->expects(self::once())->method('clean');

        $this->patch()->apply();
    }

    #[Test]
    public function withoutAConfigRoleNothingIsWritten(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);
        $this->connection->expects(self::never())->method('insertMultiple');
        $this->connection->expects(self::never())->method('delete');

        $this->patch()->apply();
    }

    private function patch(): GrantSplitScreenResources
    {
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);

        return new GrantSplitScreenResources($setup, $this->aclCache);
    }
}
