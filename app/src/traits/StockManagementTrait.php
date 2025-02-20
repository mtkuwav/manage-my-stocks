<?php

namespace App\Traits;

use App\Utils\HttpException;

trait StockManagementTrait {
    /**
     * Validate that the stock quantity is not negative
     * @param int $quantity The quantity to validate
     * @throws HttpException if quantity is negative
     * @return void
     * @author Mathieu Chauvet
     */
    protected function validateStock(int $quantity): void {
        if ($quantity < 0) {
            throw new HttpException("Stock quantity cannot be negative", 400);
        }
    }

    /**
     * Generate a unique SKU (Stock Keeping Unit) for a product
     * @param string $categoryPrefix The prefix representing the product category
     * @param string $name The product name
     * @return string The generated SKU in format "CATEGORY-NAME-TIMESTAMP"
     * @author Mathieu Chauvet
     */
    protected function generateSKU(string $categoryPrefix, string $name): string {
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $namePrefix = substr(strtoupper($cleanName), 0, 3);
        $timestamp = time();
        return sprintf("%s-%s-%d", strtoupper($categoryPrefix), $namePrefix, $timestamp);
    }

    /**
     * Validate that both old and new stock quantities are not negative
     * @param int $oldQuantity The current stock quantity
     * @param int $newQuantity The new stock quantity to validate
     * @throws HttpException if new quantity is negative or current stock is invalid
     * @return void
     * @author Mathieu Chauvet
     */
    protected function validateStockChange(int $oldQuantity, int $newQuantity): void {
        if ($newQuantity < 0) {
            throw new HttpException("New stock quantity cannot be negative", 400);
        }
        
        if ($oldQuantity < 0) {
            throw new HttpException("Current stock quantity is invalid", 500);
        }
    }
}