<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Utils\{Route, HttpException, JWT};
use App\Models\ReturnsModel;
use App\Middlewares\AuthMiddleware;

class Returns extends Controller {
    private ReturnsModel $return;

    public function __construct($params) {
        parent::__construct($params);
        $this->return = new ReturnsModel();
    }

    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a return request
     * 
     * @throws HttpException if required fields are missing
     * @return array The created return data
     * @author Mathieu Chauvet
     */
    #[Route("POST", "/returns", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function createReturn() {
        try {
            if (!isset($this->body['order_item_id'], $this->body['quantity_returned'])) {
                throw new HttpException("Missing required fields", 400);
            }

            return $this->return->create($this->body);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Process a return request
     * 
     * @throws HttpException if status is missing or unauthorized
     * @return array The processed return data
     * @author Mathieu Chauvet
     */
    #[Route("PATCH", "/returns/:id", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function processReturn() {
        try {
            $id = intval($this->params['id']);
            if (!isset($this->body['status'])) {
                throw new HttpException("Status is required", 400);
            }

            // Get admin ID from JWT token
            $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
            $payload = JWT::decryptToken($token);
            $userId = $payload['user_id'] ?? null;

            if (!$userId) {
                throw new HttpException("Unauthorized", 401);
            }

            return $this->return->processReturn($id, $this->body['status'], $userId);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get returns statistics
     * 
     * @return array Returns statistics data
     * @throws HttpException if there's an error retrieving statistics
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/returns/statistics", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getStatistics() {
        try {
            $filters = [
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null
            ];
            return $this->return->getStatistics(array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Get all returns
     * 
     * @return array Array of all returns matching the applied filters
     * @throws HttpException if there's an error retrieving returns
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/returns", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getReturns() {
        try {
            $filters = [
                'status' => $this->query['status'] ?? null,
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null,
                'limit' => $this->query['limit'] ?? null
            ];
            return $this->return->getAll(array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Get return by ID
     * 
     * @return array The return data
     * @throws HttpException if return not found
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/returns/:id", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getReturn() {
        try {
            $id = intval($this->params['id']);
            $return = $this->return->getById($id);
            
            if (!$return) {
                throw new HttpException("Return not found", 404);
            }
            
            return $return;
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Get returns by product
     * 
     * @return array Array of returns for the specified product
     * @throws HttpException if there's an error retrieving returns
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/products/:id/returns", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function getProductReturns() {
        try {
            $productId = intval($this->params['id']);
            $filters = [
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null,
                'status' => $this->query['status'] ?? null,
                'limit' => $this->query['limit'] ?? null
            ];
            return $this->return->getByProduct($productId, array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        }
    }
}