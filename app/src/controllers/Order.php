<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Utils\{HttpException, Route, JWT};
use App\Models\OrderModel;
use App\Middlewares\AuthMiddleware;

class Order extends Controller {
    protected OrderModel $order;
    protected ?array $decodedToken;

    public function __construct($params) {
        parent::__construct($params);
        $this->order = new OrderModel();
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            try {
                $this->decodedToken = (array) JWT::decryptToken($matches[1]);
            } catch (\Exception $e) {
                throw new HttpException("Invalid token: " . $e->getMessage(), 401);
            }
        } else {
            $this->decodedToken = null;
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new order.
     * 
     * @throws HttpException if there's an error during order creation
     * @return array The created order data
     * @author Mathieu Chauvet
     */
    #[Route("POST", "/orders", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function createOrder() {
        try {
            $data = json_decode(json_encode($this->body), true);

            if (!$this->decodedToken || !isset($this->decodedToken['user_id'])) {
                throw new HttpException("User authentication required", 401);
            }

            $data['user_id'] = $this->decodedToken['user_id'];
    
            if (!isset($data['items']) || !is_array($data['items'])) {
                throw new HttpException("Items array is required", 400);
            }
    
            foreach ($data['items'] as $item) {
                if (!isset($item['product_id'], $item['quantity'])) {
                    throw new HttpException("Each item must have product_id and quantity", 400);
                }
                if ($item['quantity'] <= 0) {
                    throw new HttpException("Quantity must be greater than 0", 400);
                }
            }
            
            return $this->order->create($data);
        } catch (HttpException $e) {
            throw $e;
        }
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get order statistics
     * 
     * @return array Order statistics including total orders, revenue, and other metrics
     * @throws HttpException if there's an error retrieving statistics
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/orders/statistics", middlewares: [AuthMiddleware::class], allowedRoles: ['admin'])]
    public function getOrderStatistics() {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'user_id' => $_GET['user_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null
            ];

            return $this->order->getStatistics(array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Get a specific order by ID.
     *
     * @return array The order data
     * @throws HttpException if order not found
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/orders/:id", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getOrder() {
        try {
            $id = intval($this->params['id']);
            return $this->order->getById($id);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Get all orders with optional filtering
     * 
     * @return array Array of all orders matching the applied filters
     * @throws HttpException if there's an error retrieving orders
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/orders", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getAllOrders() {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'user_id' => $_GET['user_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null
            ];

            if (isset($filters['limit'])) {
                $filters['limit'] = (int)$filters['limit'];
            }
            
            return $this->order->getAll(array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Update order status.
     *
     * @throws HttpException if status is missing or update fails
     * @return array The updated order data
     * @author Mathieu Chauvet
     */
    #[Route("PATCH", "/orders/:id/status", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function updateOrderStatus() {
        try {
            $id = intval($this->params['id']);
            if (empty($this->body['status'])) {
                throw new HttpException("Status is required", 400);
            }
            return $this->order->updateStatus($id, $this->body['status']);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Cancel an order and restore product stock quantities
     * 
     * @return array The cancelled order data
     * @throws HttpException if order cancellation fails
     */
    #[Route("POST", "/orders/:id/cancel", middlewares: [AuthMiddleware::class], allowedRoles: ['admin'])]
    public function cancelOrder() {
        try {
            $id = intval($this->params['id']);
            return $this->order->cancelOrder($id);
        } catch (HttpException $e) {
            throw $e;
        }
    }
}