<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api;

final class SearchCriteriaQuery
{
    private const DEFAULT_DIRECTION = 'ASC';

    /**
     * @param array<int, array<string, mixed>> $filters
     * @param array<int, array<string, mixed>>|null $sortOrders
     * @return array<string, mixed>
     */
    public function build(array $filters, int $pageSize, int $currentPage, ?array $sortOrders): array
    {
        return [
            'searchCriteria[pageSize]' => $pageSize,
            'searchCriteria[currentPage]' => $currentPage,
        ] + $this->filterParams($filters) + $this->sortParams($sortOrders ?? []);
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     * @return array<string, mixed>
     */
    private function filterParams(array $filters): array
    {
        $params = [];
        foreach ($filters as $groupIndex => $filter) {
            $prefix = "searchCriteria[filter_groups][$groupIndex][filters][0]";
            $params[$prefix . '[field]'] = $filter['field'];
            $params[$prefix . '[value]'] = $filter['value'];
            if (isset($filter['condition_type'])) {
                $params[$prefix . '[conditionType]'] = $filter['condition_type'];
            }
        }

        return $params;
    }

    /**
     * @param array<int, array<string, mixed>> $sortOrders
     * @return array<string, mixed>
     */
    private function sortParams(array $sortOrders): array
    {
        $params = [];
        foreach ($sortOrders as $index => $sort) {
            $prefix = "searchCriteria[sortOrders][$index]";
            $params[$prefix . '[field]'] = $sort['field'];
            $params[$prefix . '[direction]'] = $sort['direction'] ?? self::DEFAULT_DIRECTION;
        }

        return $params;
    }
}
