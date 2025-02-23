<?php

namespace App\Traits;

trait FilterableTrait {
    /**
     * Build filter conditions for queries
     * 
     * @param array $filters Associative array of filters
     * @param string $tableAlias Table alias in query (e.g., 'o' for orders)
     * @return array ['conditions' => [], 'params' => []]
     */
    protected function buildFilterConditions(?array $filters, string $tableAlias = ''): array {
        $conditions = [];
        $params = [];
        
        if (!$filters) {
            return ['conditions' => $conditions, 'params' => $params];
        }

        $prefix = $tableAlias ? "$tableAlias." : '';

        // Status filter
        if (isset($filters['status'])) {
            $conditions[] = "{$prefix}status = :status";
            $params['status'] = $filters['status'];
        }

        // User filter
        if (isset($filters['user_id'])) {
            $conditions[] = "{$prefix}user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        // Date range filters
        if (isset($filters['date_from'])) {
            $conditions[] = "{$prefix}created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = "{$prefix}created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        return [
            'conditions' => $conditions,
            'params' => $params
        ];
    }
}