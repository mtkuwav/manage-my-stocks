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

}