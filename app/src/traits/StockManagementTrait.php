<?php

namespace App\Traits;

use App\Utils\HttpException;

trait StockManagementTrait {
    /**
     * Validate product stock quantity
     */
    protected function validateStock(int $quantity): void {
        if ($quantity < 0) {
            throw new HttpException("Stock quantity cannot be negative", 400);
        }
    }

    /**
     * Generate a unique SKU
     */
    protected function generateSKU(string $categoryPrefix, string $name): string {
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $namePrefix = substr(strtoupper($cleanName), 0, 3);
        $timestamp = time();
        return sprintf("%s-%s-%d", strtoupper($categoryPrefix), $namePrefix, $timestamp);
    }
}