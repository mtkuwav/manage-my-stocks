<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\ProductModel;
use App\Utils\Route;
use App\Utils\HttpException;
use App\Middlewares\AuthMiddleware;

class Product extends Controller {
    protected ProductModel $product;

    public function __construct($params) {
        parent::__construct($params);
        $this->product = new ProductModel();
    }


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new product.
     * 
     * @throws HttpException if there's an error during product creation
     * @return array The created product data
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("POST", "/products", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function createProduct() {
        try {
            return $this->product->createProduct($this->body);
        } catch (HttpException $e) {
            throw $e;
        }
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get a specific product by ID.
     *
     * @return array The product data
     * @throws HttpException if product not found
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("GET", "/products/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function getById() {
        try {
            $id = intval($this->params['id']);
            return $this->product->getById($id);
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Get all products with optional limit.
     *
     * @return array Array of product records
     * @author Rémis Rubis, Mathieu Chauvet 
     */
    #[Route("GET", "/products", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function getAll() {
        try {
            $filters = [
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null,
                'limit' => $this->query['limit'] ?? null,
                'category_id' => $this->query['category_id'] ?? null,
                'price_min' => $this->query['price_min'] ?? null,
                'price_max' => $this->query['price_max'] ?? null
            ];
            return $this->product->getAll(array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        }
    }

    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Update product information.
     *
     * @throws HttpException if the request body is empty or contains no valid fields
     * @return array The updated product data
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("PATCH", "/products/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function updateProduct() {
        try {
            $id = intval($this->params['id']);
            $data = $this->body;

            if (empty($data)) {
                throw new HttpException("Missing parameters for the update.", 400);
            }

            $allowedFields = array_intersect_key($data, array_flip($this->product->authorized_fields_to_update));
            if (empty($allowedFields)) {
                throw new HttpException("No valid fields to update.", 400);
            }

            return $this->product->update($allowedFields, $id);

        } catch (HttpException $e) {
            throw $e;
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- DELETE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Delete a product by ID.
     *
     * @throws HttpException if deletion fails
     * @return array Success message
     * @author Mathieu Chauvet
     */
    #[Route("DELETE", "/products/:id", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function deleteProduct() {
        try {
            $id = intval($this->params['id']);
            return $this->product->delete($id);
        } catch (HttpException $e) {
            throw $e;
        }
    }
}