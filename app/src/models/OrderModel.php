<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use App\Traits\FilterableTrait;
use PDO;

class OrderModel extends SqlConnect {
    use FilterableTrait;

    private string $ordersTable = 'orders';
    private string $orderItemsTable = 'order_items';

    private array $validOrderStatuses = [
        'pending' => 'Order is awaiting processing',
        'processing' => 'Order is being processed',
        'completed' => 'Order has been completed',
        'cancelled' => 'Order has been cancelled'
    ];



    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new order
     * 
     * @param array|object $data Order data containing user_id and items
     * @return array The created order data
     * @throws HttpException If validation fails or creation fails
     * @author Mathieu Chauvet
     */
    public function create(array|object $data): array {
        try {
            $orderData = is_object($data) ? json_decode(json_encode($data), true) : $data;

            if (empty($orderData['user_id'])) {
                throw new HttpException("User ID is required", 400);
            }

            if (empty($orderData['items'])) {
                throw new HttpException("Order items are required", 400);
            }
            
            try {
                $totalAmount = $this->validateAndCalculateItems($orderData['items']);

                $stmt = $this->db->prepare(
                    "INSERT INTO $this->ordersTable 
                    (user_id, total_amount, status) 
                    VALUES (:user_id, :total_amount, 'pending')"
                );
                
                $stmt->execute([
                    'user_id' => $orderData['user_id'],
                    'total_amount' => $totalAmount
                ]);

                $orderId = $this->db->lastInsertId();
                $this->createOrderItems($orderId, $orderData['items']);

                return $this->getById($orderId);

            } catch (\Exception $e) {
                throw $e;
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get order by ID with its items
     * 
     * @param int $id The ID of the order to retrieve
     * @return array The order data with items
     * @throws HttpException If order is not found
     * @author Mathieu Chauvet
     */
    public function getById(int $id) {
        $query = "SELECT o.*, 
                    u.username as user_name
                FROM $this->ordersTable o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new HttpException("Order not found", 404);
        }

        $query = "SELECT oi.*,
                    p.name as product_name,
                    p.sku as product_sku,
                    p.price as current_price
                FROM $this->orderItemsTable oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['order_id' => $id]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    /**
     * Get all orders with optional filters
     * 
     * @param array|null $filters Optional filters to apply (status, user_id, date_from, date_to)
     * @return array Array of orders matching the filters
     * @author Mathieu Chauvet
     */
    public function getAll(?array $filters = null) {
        $query = "SELECT o.*, u.username as user_name 
                FROM $this->ordersTable o
                LEFT JOIN users u ON o.user_id = u.id";

        $filterData = $this->buildFilterConditions($filters, 'o');
            
        if (!empty($filterData['conditions'])) {
            $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
        }
        
        $query .= " ORDER BY o.created_at DESC" . $filterData['limit'];
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($filterData['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get order statistics with optional filters
     * 
     * @param array|null $filters Optional filters to apply (date range, status, etc.)
     * @return array Statistics including total orders, revenue, completed/cancelled orders, and average order value
     * @author Mathieu Chauvet
     */
    public function getStatistics(?array $filters = null): array {
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                    AVG(total_amount) as average_order_value
                FROM $this->ordersTable o";

        $filterData = $this->buildFilterConditions($filters, 'o');
                
        if (!empty($filterData['conditions'])) {
            $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($filterData['params']);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Update order status
     * 
     * @param int $id The ID of the order
     * @param string $status The new status
     * @return array The updated order data
     * @throws HttpException If status is invalid or update fails
     * @author Mathieu Chauvet
     */
    public function updateStatus(int $id, string $status) {
        $currentOrder = $this->getById($id);
    
        if ($currentOrder['status'] === 'cancelled') {
            throw new HttpException("Cannot update status of cancelled order", 400);
        }

        if (!array_key_exists($status, $this->validOrderStatuses)) {
            throw new HttpException("Invalid status", 400);
        }

        $stmt = $this->db->prepare("UPDATE $this->ordersTable SET status = :status WHERE id = :id");
        $success = $stmt->execute(['status' => $status, 'id' => $id]);

        if (!$success) {
            throw new HttpException("Failed to update order status", 500);
        }

        return $this->getById($id);
    }

    /**
     * Cancel an order and restore product stock quantities
     * 
     * @param int $id The ID of the order to cancel
     * @return array The cancelled order data
     * @throws HttpException If order is not found or cancellation fails
     * @author Mathieu Chauvet
     */
    public function cancelOrder(int $id): array {
        try {
            $order = $this->getById($id);
            if ($order['status'] === 'cancelled') {
                throw new HttpException("Order is already cancelled", 400);
            }

            $stmt = $this->db->prepare(
                "UPDATE $this->ordersTable SET status = 'cancelled' WHERE id = ?"
            );
            $stmt->execute([$id]);

            foreach ($order['items'] as $item) {
                // Get current stock
                $stmt = $this->db->prepare("SELECT quantity_in_stock FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $currentStock = (int)$stmt->fetch(PDO::FETCH_COLUMN);

                $newStock = $currentStock + $item['quantity'];
                $stmt = $this->db->prepare(
                    "UPDATE products 
                    SET quantity_in_stock = :new_quantity 
                    WHERE id = :product_id"
                );
                
                $stmt->execute([
                    'new_quantity' => $newStock,
                    'product_id' => $item['product_id']
                ]);

                $stmt = $this->db->prepare("SELECT quantity_in_stock FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $updatedStock = (int)$stmt->fetch(PDO::FETCH_COLUMN);
    
                if ($updatedStock !== $newStock) {
                    throw new HttpException(
                        "Stock restoration failed for product {$item['product_id']}. " .
                        "Expected: $newStock, Got: $updatedStock", 
                        500
                    );
                }
            }
            return $this->getById($id);
        } catch (\Exception $e) {
            throw new HttpException("Failed to cancel order: " . $e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- HELPER METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Validate and calculate total amount for order items
     * 
     * @param array|object $items Array or object containing order items
     * @return float The total amount calculated
     * @throws HttpException If items are invalid or stock is insufficient
     * @author Mathieu Chauvet
     */
    private function validateAndCalculateItems(array|object $items): float {
        $itemsArray = is_object($items) ? json_decode(json_encode($items), true) : $items;
        
        if (!is_array($itemsArray)) {
            throw new HttpException("Items must be an array or object", 400);
        }

        $totalAmount = 0;
        foreach ($itemsArray as $item) {
            $item = is_object($item) ? json_decode(json_encode($item), true) : $item;
            
            if (!isset($item['product_id'], $item['quantity'])) {
                throw new HttpException("Invalid item format", 400);
            }

            if ($item['quantity'] <= 0) {
                throw new HttpException("Quantity must be greater than 0", 400);
            }

            $product = $this->getProductForOrder($item['product_id']);
            
            if ($product['quantity_in_stock'] < $item['quantity']) {
                throw new HttpException(
                    "Insufficient stock for product: {$item['product_id']}", 
                    400
                );
            }

            $totalAmount += $product['price'] * $item['quantity'];
        }
        return $totalAmount;
    }

    /**
     * Get product information for order processing
     * 
     * @param int $productId The ID of the product to retrieve
     * @return array Product information
     * @throws HttpException If product is not found
     * @author Mathieu Chauvet
     */
    private function getProductForOrder(int $productId): array {
        $stmt = $this->db->prepare(
            "SELECT price, quantity_in_stock FROM products WHERE id = ?"
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new HttpException("Product not found: {$productId}", 404);
        }

        return $product;
    }

    /**
     * Create order items and update stock
     * 
     * @param int $orderId The ID of the order
     * @param array|object $items The items to create
     * @author Mathieu Chauvet
     */
    private function createOrderItems(int $orderId, array|object $items): void {
        $itemsArray = is_object($items) ? json_decode(json_encode($items), true) : $items;
        
        foreach ($itemsArray as $item) {
            $itemData = is_object($item) ? json_decode(json_encode($item), true) : $item;

            $stmt = $this->db->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$itemData['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->insertOrderItem($orderId, $itemData, $product['price']);

            $this->updateProductStock($itemData['product_id'], $itemData['quantity']);
        }
    }

    /**
     * Insert a single order item
     * 
     * @param int $orderId The ID of the order
     * @param array $item The item data
     * @param float $unitPrice The unit price of the item
     * @author Mathieu Chauvet
     */
    private function insertOrderItem(int $orderId, array $item, float $unitPrice): void {
        $stmt = $this->db->prepare(
            "INSERT INTO $this->orderItemsTable 
            (order_id, product_id, quantity, unit_price) 
            VALUES (:order_id, :product_id, :quantity, :unit_price)"
        );
        
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $unitPrice
        ]);
    }

    /**
     * Update product stock quantity
     * 
     * @param int $productId The ID of the product
     * @param int $quantity The quantity to subtract from stock
     * @author Mathieu Chauvet
     */
    private function updateProductStock(int $productId, int $quantity): void {
        $stmt = $this->db->prepare(
            "UPDATE products 
            SET quantity_in_stock = quantity_in_stock - :quantity 
            WHERE id = :product_id"
        );
        
        $stmt->execute([
            'quantity' => $quantity,
            'product_id' => $productId
        ]);
    }
}