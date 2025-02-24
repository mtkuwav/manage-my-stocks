<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Utils\{Route, HttpException};
use App\Models\DeliveryModel;
use App\Middlewares\AuthMiddleware;

class Delivery extends Controller {
    private DeliveryModel $delivery;

    public function __construct($params) {
        parent::__construct($params);
        $this->delivery = new DeliveryModel();
    }

    #[Route("POST", "/deliveries", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function createDelivery() {
        try {
            if (!isset($this->body['order_id'])) {
                throw new HttpException("Order ID is required", 400);
            }
            return $this->delivery->create($this->body);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    #[Route("PATCH", "/deliveries/:id", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function updateDeliveryStatus() {
        try {
            $id = intval($this->params['id']);
            if (!isset($this->body['status'])) {
                throw new HttpException("Status is required", 400);
            }
            return $this->delivery->updateStatus($id, $this->body['status']);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    #[Route("GET", "/deliveries/:id", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getDelivery() {
        try {
            $id = intval($this->params['id']);
            $delivery = $this->delivery->getById($id);
            
            if (!$delivery) {
                throw new HttpException("Delivery not found", 404);
            }
            
            return $delivery;
        } catch (HttpException $e) {
            throw $e;
        }
    }

    #[Route("GET", "/deliveries", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getDeliveries() {
        try {
            $filters = array_filter([
                'status' => $_GET['status'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null
            ]);
            return $this->delivery->getAll($filters);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    #[Route("GET", "/orders/:id/deliveries", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getOrderDeliveries() {
        try {
            $orderId = intval($this->params['id']);
            $filters = array_filter([
                'status' => $_GET['status'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null
            ]);
            return $this->delivery->getByOrder($orderId, $filters);
        } catch (HttpException $e) {
            throw $e;
        }
    }
}