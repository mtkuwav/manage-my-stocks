<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Utils\{HttpException, Route};
use App\Models\OrderModel;
use App\Middlewares\AuthMiddleware;

class Order extends Controller {
    protected OrderModel $order;

    public function __construct($params) {
        parent::__construct($params);
        $this->order = new OrderModel();
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
            return $this->order->create($this->body);
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
                'status' => $this->query['status'] ?? null,
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null
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
                'status' => $this->query['status'] ?? null,
                'user_id' => $this->query['user_id'] ?? null,
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null
            ];
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