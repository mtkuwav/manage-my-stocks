<?php

namespace App\Traits;

trait FilterableTrait {
    /**
     * Build filter conditions for queries
     * 
     * @param array $filters Associative array of filters
     * @param string $tableAlias Table alias in query (e.g., 'o' for orders)
     * @param string $joinAlias Join alias in query (e.g., 'p' for products)
     * @return array ['conditions' => [], 'params' => []]
     */
    protected function buildFilterConditions(?array $filters, string $tableAlias = '', string $joinAlias = ''): array {
        $conditions = [];
        $params = [];
        $limit = '';
        
        if (!$filters) {
            return ['conditions' => $conditions, 'params' => $params, 'limit' => ''];
        }

        $mainPrefix = $tableAlias ? "$tableAlias." : '';
        $joinPrefix = $joinAlias ? "$joinAlias." : '';

        // Role filter
        if (isset($filters['role'])) {
            $prefix = $joinAlias ? $joinPrefix : $mainPrefix;
            $conditions[] = "{$mainPrefix}role = :role";
            $params['role'] = $filters['role'];
        }

        // Product ID filter (using join table alias if provided)
        if (isset($filters['product_id'])) {
            $prefix = $joinAlias ? $joinPrefix : $mainPrefix;
            $conditions[] = "{$prefix}product_id = :product_id";
            $params['product_id'] = (int)$filters['product_id'];
        }

        // Status filter (always uses main table)
        if (isset($filters['status'])) {
            $conditions[] = "{$mainPrefix}status = :status";
            $params['status'] = $filters['status'];
        }

        // Date filters (always use main table)
        if (isset($filters['date_from'])) {
            $conditions[] = "DATE({$mainPrefix}created_at) = :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            if ($filters['date_from'] !== $filters['date_to']) {
                $conditions[] = "DATE({$mainPrefix}created_at) <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }
        }

        // Limit handling
        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $limit = " LIMIT " . (int)$filters['limit']; // Plus besoin de paramètre bindé pour LIMIT
        }

        // Inventory Log specific filters
        if (isset($filters['change_type'])) {
            $conditions[] = "{$prefix}change_type = :change_type";
            $params['change_type'] = $filters['change_type'];
        }

        // Product specific filters (optionnels)
        if (isset($filters['category_id'])) {
            $conditions[] = "{$prefix}category_id = :category_id";
            $params['category_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['price_min'])) {
            $conditions[] = "{$prefix}price >= :price_min";
            $params['price_min'] = (float)$filters['price_min'];
        }

        if (isset($filters['price_max'])) {
            $conditions[] = "{$prefix}price <= :price_max";
            $params['price_max'] = (float)$filters['price_max'];
        }

        return [
            'conditions' => $conditions,
            'params' => $params,
            'limit' => $limit
        ];
    }
}