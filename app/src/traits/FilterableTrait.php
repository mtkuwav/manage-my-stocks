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
        $limit = '';
        
        if (!$filters) {
            return ['conditions' => $conditions, 'params' => $params, 'limit' => ''];
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

        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $limit = " LIMIT :limit";
            $params['limit'] = (int)$filters['limit'];
        }

        // Inventory Log specific filters
        if (isset($filters['change_type'])) {
            $conditions[] = "{$prefix}change_type = :change_type";
            $params['change_type'] = $filters['change_type'];
        }

        if (isset($filters['product_id'])) {
            $conditions[] = "{$prefix}product_id = :product_id";
            $params['product_id'] = (int)$filters['product_id'];
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