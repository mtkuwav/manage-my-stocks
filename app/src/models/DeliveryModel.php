<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use App\Traits\FilterableTrait;
use PDO;

class DeliveryModel extends SqlConnect {
    use FilterableTrait;

    private string $deliveriesTable = 'deliveries';
    private array $validStatuses = [
        'pending' => 'Pending',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'failed' => 'Failed'
    ];


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new delivery for a completed order
     * 
     * @param array $data Delivery data containing order_id
     * @return array The created delivery data
     * @throws HttpException If order not found, not completed, or delivery creation fails
     * @author Mathieu Chauvet
     */
    public function create(array $data): array {
        try {
            // Verify order exists and is completed
            $stmt = $this->db->prepare(
                "SELECT status FROM orders WHERE id = ?"
            );
            $stmt->execute([$data['order_id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new HttpException("Order not found", 404);
            }

            if ($order['status'] !== 'completed') {
                throw new HttpException("Can only create delivery for completed orders", 400);
            }

            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->deliveriesTable} WHERE order_id = ?"
            );
            $stmt->execute([$data['order_id']]);
            if ($stmt->fetch()) {
                throw new HttpException("A delivery already exists for this order", 400);
            }

            $trackingNumber = $this->generateTrackingNumber($data['order_id']);

            $expectedDeliveryDate = date('Y-m-d', strtotime('+5 weekdays'));

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->deliveriesTable} 
                (order_id, tracking_number, expected_delivery_date) 
                VALUES (:order_id, :tracking_number, :expected_delivery_date)"
            );

            $stmt->execute([
                'order_id' => $data['order_id'],
                'tracking_number' => $trackingNumber,
                'expected_delivery_date' => $expectedDeliveryDate
            ]);

            return $this->getById($this->db->lastInsertId());
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException("Failed to create delivery: " . $e->getMessage(), 500);
        }
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get delivery by ID
     * 
     * @param int $id The ID of the delivery to retrieve
     * @return array|null The delivery data or null if not found
     * @author Mathieu Chauvet
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT d.*, o.user_id, o.status as order_status
            FROM deliveries d
            JOIN orders o ON d.order_id = o.id
            WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all deliveries with optional filters
     * 
     * @param array|null $filters Optional filters to apply
     * @return array Array of deliveries matching the filters
     * @throws HttpException If fetching deliveries fails
     * @author Mathieu Chauvet
     */
    public function getAll(?array $filters = null): array {
        try {
            $query = "SELECT d.*, o.user_id, o.status as order_status
                    FROM deliveries d
                    JOIN orders o ON d.order_id = o.id";

            $filterData = $this->buildFilterConditions($filters, 'd');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }
            
            $query .= " ORDER BY d.created_at DESC" . $filterData['limit'];

            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new HttpException("Failed to fetch deliveries: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get deliveries for a specific order
     * 
     * @param int $orderId The ID of the order
     * @param array|null $filters Optional filters to apply
     * @return array Array of deliveries for the specified order
     * @throws HttpException If fetching order deliveries fails
     * @author Mathieu Chauvet
     */
    public function getByOrder(int $orderId, ?array $filters = null): array {
        try {
            $filters = $filters ?? [];
            $filters['order_id'] = $orderId;

            return $this->getAll($filters);
        } catch (\Exception $e) {
            throw new HttpException("Failed to fetch order deliveries: " . $e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Update delivery status
     * 
     * @param int $id The ID of the delivery
     * @param string $status The new status
     * @return array The updated delivery data
     * @throws HttpException If delivery not found, invalid status, or update fails
     * @author Mathieu Chauvet
     */
    public function updateStatus(int $id, string $status): array {
        try {
            $delivery = $this->getById($id);
            
            if (!$delivery) {
                throw new HttpException("Delivery not found", 404);
            }

            if (!array_key_exists($status, $this->validStatuses)) {
                throw new HttpException("Invalid status", 400);
            }

            $params = [
                'id' => $id,
                'status' => $status,
                'actual_delivery_date' => null
            ];

            if ($status === 'delivered') {
                $params['actual_delivery_date'] = date('Y-m-d H:i:s');
            }

            $stmt = $this->db->prepare(
                "UPDATE {$this->deliveriesTable} 
                SET status = :status, 
                    actual_delivery_date = :actual_delivery_date
                WHERE id = :id"
            );

            $stmt->execute($params);
            return $this->getById($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException("Failed to update delivery: " . $e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- HELPER METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Generate a unique tracking number for a delivery
     * 
     * @param int $orderId The ID of the order
     * @return string The generated tracking number
     * @throws HttpException If order not found or generation fails
     * @author Mathieu Chauvet
     */
    private function generateTrackingNumber(int $orderId): string {
        try {
            // Get order details for tracking number generation
            $stmt = $this->db->prepare(
                "SELECT o.id, o.created_at, u.id as user_id 
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = ?"
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$order) {
                throw new HttpException("Order not found", 404);
            }
    
            // Format: DEL-YYYYMMDD-ORDERID-USERID-RANDOM
            $date = date('Ymd', strtotime($order['created_at']));
            $randomPart = strtoupper(substr(uniqid(), -4));
            
            return sprintf(
                "DEL-%s-%04d-%04d-%s",
                $date,
                $order['id'],
                $order['user_id'],
                $randomPart
            );
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException("Failed to generate tracking number: " . $e->getMessage(), 500);
        }
    }
}