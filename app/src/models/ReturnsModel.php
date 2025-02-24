<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use App\Traits\FilterableTrait;
use PDO;

class ReturnsModel extends SqlConnect {
    use FilterableTrait;

    private string $returnsTable = 'returns';
    private array $validStatuses = [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'refunded' => 'Refunded'
    ];


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new return request
     * 
     * @param array $data Return request data including order_item_id and quantity_returned
     * @return array The created return request data
     * @throws HttpException If validation fails or creation fails
     */
    public function create(array $data) {
        try {   
            // Verify order item exists and quantity is valid
            $stmt = $this->db->prepare(
                "SELECT oi.*, o.status as order_status 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.id = ?"
            );
            $stmt->execute([$data['order_item_id']]);
            $orderItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderItem) {
                throw new HttpException("Order item not found", 404);
            }
    
            if ($orderItem['order_status'] !== 'completed') {
                throw new HttpException("Can only return items from completed orders", 400);
            }
    
            // Create return request
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->returnsTable} 
                (order_item_id, quantity_returned, reason, status) 
                VALUES (:order_item_id, :quantity_returned, :reason, 'requested')"
            );
    
            $params = [
                'order_item_id' => $data['order_item_id'],
                'quantity_returned' => $data['quantity_returned'],
                'reason' => $data['reason'] ?? null
            ];
    
            $stmt->execute($params);
            $returnId = $this->db->lastInsertId();
    
            return $this->getById($returnId);
        } catch (HttpException $e) {
            throw $e; 
        } catch (\Exception $e) {
            throw new HttpException("Failed to create return: " . $e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Process a return request (approve/reject)
     * 
     * @param int $id Return request ID
     * @param string $status New status (approved/rejected)
     * @param int $processedBy User ID of the processor
     * @return array Updated return request data
     * @throws HttpException If return not found or invalid status
     */
    public function processReturn(int $id, string $status, int $processedBy): array {
        try {
            $return = $this->getById($id);
            if (!$return) {
                throw new HttpException("Return request not found", 404);
            }

            if ($return['status'] !== 'requested') {
                throw new HttpException("Return request has already been processed", 400);
            }

            if (!array_key_exists($status, $this->validStatuses)) {
                throw new HttpException("Invalid status", 400);
            }

            // Update return status
            $stmt = $this->db->prepare(
                "UPDATE {$this->returnsTable} 
                SET status = :status, processed_by = :processed_by 
                WHERE id = :id"
            );
            $stmt->execute([
                'id' => $id,
                'status' => $status,
                'processed_by' => $processedBy
            ]);

            // If approved, update product stock
            if ($status === 'approved') {
                $stmt = $this->db->prepare(
                    "UPDATE products p 
                    JOIN order_items oi ON oi.product_id = p.id 
                    JOIN {$this->returnsTable} r ON r.order_item_id = oi.id 
                    SET p.quantity_in_stock = p.quantity_in_stock + r.quantity_returned 
                    WHERE r.id = ?"
                );
                $stmt->execute([$id]);
            }

            return $this->getById($id);
        } catch (\Exception $e) {
            throw $e;
        }
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get all returns with optional filters
     * 
     * @param array|null $filters Optional filters to apply (status, date_from, date_to, limit)
     * @return array Array of returns matching the filters
     * @throws HttpException If fetching fails
     */
    public function getAll(?array $filters = null): array {
        try {
            $query = "SELECT DISTINCT r.*, u.username as processed_by_username,
                    oi.product_id, oi.quantity as ordered_quantity,
                    p.name as product_name, p.sku as product_sku
                    FROM {$this->returnsTable} r
                    JOIN order_items oi ON r.order_item_id = oi.id
                    JOIN products p ON oi.product_id = p.id
                    LEFT JOIN users u ON r.processed_by = u.id";

            $filterData = $this->buildFilterConditions($filters, 'r');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }
            
            $query .= " ORDER BY r.created_at DESC" . $filterData['limit'];

            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new HttpException("Failed to fetch returns: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get return by ID
     * 
     * @param int $id Return request ID
     * @return array|null Return request data or null if not found
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT r.*, u.username as processed_by_username,
            oi.quantity as ordered_quantity, oi.product_id,
            p.name as product_name, p.sku as product_sku
            FROM {$this->returnsTable} r
            JOIN order_items oi ON r.order_item_id = oi.id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u ON r.processed_by = u.id
            WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get returns statistics
     * 
     * @param array|null $filters Optional filters (date range, status)
     * @return array Statistics including total returns, approved/rejected counts, etc.
     * @throws HttpException If fetching statistics fails
     */
    public function getStatistics(?array $filters = null): array {
        try {
            $query = "SELECT 
                COUNT(*) as total_returns,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_returns,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_returns,
                COUNT(CASE WHEN status = 'requested' THEN 1 END) as pending_returns,
                SUM(CASE WHEN status = 'approved' THEN quantity_returned ELSE 0 END) as total_items_returned,
                AVG(CASE WHEN status = 'approved' THEN quantity_returned ELSE NULL END) as avg_return_quantity
            FROM {$this->returnsTable} r";
    
            $filterData = $this->buildFilterConditions($filters, 'r');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }
    
            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new HttpException("Failed to fetch statistics: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get returns for a specific product
     * 
     * @param int $productId Product ID to get returns for
     * @param array|null $filters Optional filters to apply
     * @return array Array of returns for the specified product
     * @throws HttpException If fetching product returns fails
     */
    public function getByProduct(int $productId, ?array $filters = null): array {
        try {
            // Ajouter product_id aux filtres
            $filters = $filters ?? [];
            $filters['product_id'] = $productId;

            $query = "SELECT DISTINCT r.*, u.username as processed_by_username,
                    oi.product_id, oi.quantity as ordered_quantity,
                    p.name as product_name, p.sku as product_sku
                    FROM {$this->returnsTable} r
                    JOIN order_items oi ON r.order_item_id = oi.id
                    JOIN products p ON oi.product_id = p.id
                    LEFT JOIN users u ON r.processed_by = u.id";

            $filterData = $this->buildFilterConditions($filters, 'r', 'oi');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }
            
            $query .= " ORDER BY r.created_at DESC";
            
            if (!empty($filterData['limit'])) {
                $query .= $filterData['limit'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new HttpException("Failed to fetch product returns: " . $e->getMessage(), 500);
        }
    }
}