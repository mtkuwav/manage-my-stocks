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

            // Simplifier la validation des champs
            $allowedFields = array_intersect_key($data, array_flip($this->product->authorized_fields_to_update));
            if (empty($allowedFields)) {
                throw new HttpException("No valid fields to update.", 400);
            }

            // Laisser le modèle gérer les autres validations
            return $this->product->update($allowedFields, $id);

        } catch (HttpException $e) {
            throw $e;
        }
    }
}