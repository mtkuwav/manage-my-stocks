<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use \PDO;

class InventoryLogModel extends SqlConnect {
    private string $table = "inventory_logs";
    
    /**
     * Valid inventory change types with their descriptions
     */
    private array $validChangeTypes = [
        'adjustment' => 'Manual stock adjustment or correction',
        'sale' => 'Stock reduction due to sale',
        'return' => 'Stock increase due to product return',
        'restock' => 'Stock increase from supplier delivery',
        'initial' => 'Initial stock on product creation'
    ];

    /**
     * Get description for a change type
     */
    public function getChangeTypeDescription(string $type): ?string {
        return $this->validChangeTypes[$type] ?? null;
    }

    /**
     * Validate change type
     */
    private function validateChangeType(string $type): void {
        if (!array_key_exists($type, $this->validChangeTypes)) {
            throw new HttpException(
                "Invalid change type. Must be one of: " . implode(', ', array_keys($this->validChangeTypes)), 
                400
            );
        }
    }

    /**
     * Log a change in product inventory
     *
     * @param int $productId ID of the product
     * @param ?int $userId ID of the user making the change (null for system changes)
     * @param int $oldQuantity Previous quantity
     * @param int $newQuantity New quantity
     * @param string $changeType Type of change (adjustment|sale|return|restock)
     * @throws HttpException If the log entry could not be created
     * @return bool True if log was created successfully
     * @author Mathieu Chauvet
     */
    public function logChange(
        int $productId,
        ?int $userId,
        int $oldQuantity,
        int $newQuantity,
        string $changeType
    ): bool {
        $this->validateChangeType($changeType);

        try {
            $query = "INSERT INTO {$this->table} 
                    (product_id, user_id, old_quantity, new_quantity, change_type, created_at)
                    VALUES (:product_id, :user_id, :old_quantity, :new_quantity, :change_type, NOW())";
            
            $stmt = $this->db->prepare($query);
            $success = $stmt->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'change_type' => $changeType
            ]);

            if (!$success) {
                throw new HttpException("Failed to create inventory log entry", 500);
            }

            return true;

        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        }
    }
}