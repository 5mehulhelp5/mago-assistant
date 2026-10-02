<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Setup\Patch\Data;

use Magento\Framework\Acl\Data\CacheInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Statistics and skill permissions used to sit behind MagoAssistant_Mago::config and now have their
 * own resources (#195). A role that held the config grant keeps them, so the upgrade takes nothing
 * away silently. Conversations are left out on purpose: reading every admin's transcripts with only
 * the config grant was the gap this closed.
 */
class GrantSplitScreenResources implements DataPatchInterface
{
    private const CONFIG_RESOURCE = 'MagoAssistant_Mago::config';

    public const GRANTED_RESOURCES = [
        'MagoAssistant_Mago::statistics',
        'MagoAssistant_Mago::skills',
        'MagoAssistant_Mago::skills_read',
        'MagoAssistant_Mago::skills_write',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CacheInterface $aclCache
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('authorization_rule');

        $roleIds = $connection->fetchCol(
            $connection->select()
                ->from($table, ['role_id'])
                ->where('resource_id = ?', self::CONFIG_RESOURCE)
                ->where('permission = ?', 'allow')
        );
        if ($roleIds === []) {
            return $this;
        }

        $connection->delete($table, ['role_id IN (?)' => $roleIds, 'resource_id IN (?)' => self::GRANTED_RESOURCES]);

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach (self::GRANTED_RESOURCES as $resource) {
                $rows[] = ['role_id' => (int)$roleId, 'resource_id' => $resource, 'permission' => 'allow'];
            }
        }
        $connection->insertMultiple($table, $rows);
        $this->aclCache->clean();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
