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

    /**
     * Get order by ID
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
     * Update order status
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
}