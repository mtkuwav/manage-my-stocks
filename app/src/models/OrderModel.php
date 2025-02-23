<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use PDO;

class OrderModel extends SqlConnect {
    private string $ordersTable = 'orders';
    private string $orderItemsTable = 'order_items';

    // Statuts de commande valides
    private array $validOrderStatuses = [
        'pending' => 'Order is awaiting processing',
        'processing' => 'Order is being processed',
        'completed' => 'Order has been completed',
        'cancelled' => 'Order has been cancelled'
    ];

    /**
     * Validate and calculate total amount for order items
     */
    private function validateAndCalculateItems(array|object $items): float {
        // Convert items to array if it's an object
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
     */
    private function createOrderItems(int $orderId, array|object $items): void {
        // Convert items to array if it's an object
        $itemsArray = is_object($items) ? json_decode(json_encode($items), true) : $items;
        
        foreach ($itemsArray as $item) {
            // Convert item to array if it's an object
            $itemData = is_object($item) ? json_decode(json_encode($item), true) : $item;

            // Get current product price
            $stmt = $this->db->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$itemData['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insert order item
            $this->insertOrderItem($orderId, $itemData, $product['price']);

            // Update stock
            $this->updateProductStock($itemData['product_id'], $itemData['quantity']);
        }
    }

    /**
     * Insert a single order item
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

    /**
     * Create a new order
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
                
                // Create order
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

    /**
     * Get order by ID with its items
     */
    public function getById(int $id) {
        $query = "SELECT o.*, u.username as user_name FROM $this->ordersTable o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new HttpException("Order not found", 404);
        }

        // Get order items
        $query = "SELECT oi.*, p.name as product_name, p.sku 
                FROM $this->orderItemsTable oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['order_id' => $id]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    /**
     * Update order status
     */
    public function updateStatus(int $id, string $status) {
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
}