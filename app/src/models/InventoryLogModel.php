<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use App\Traits\FilterableTrait;
use \stdClass;
use \PDO;

class InventoryLogModel extends SqlConnect {
    use FilterableTrait;

    private string $table = "inventory_logs";
    
    /**
     * Valid inventory change types with their descriptions
     */
    private array $validChangeTypes = [
        'adjustment' => 'Manual stock adjustment or correction',
        'sale' => 'Stock reduction due to sale',
        'return' => 'Stock increase due to product return',
        'restock' => 'Stock increase from supplier delivery',
        'initial' => 'Initial stock on product creation',
        'deletion' => 'Product deletion'
    ];


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Retrieves a log by its ID.
     * 
     * @param int $id The ID of the log to retrieve
     * @return array The log data as an associative array
     * @author Mathieu Chauvet
     */
    public function getById(int $id) {
        try {
            if ($id <= 0) {
                throw new HttpException("Invalid log ID", 400);
            }

            $query = "SELECT l.*, u.username, p.name as product_name, p.sku as product_sku
                    FROM {$this->table} l 
                    LEFT JOIN users u ON l.user_id = u.id 
                    LEFT JOIN products p ON l.product_id = p.id
                    WHERE l.id = :id";
            $req = $this->db->prepare($query);
            $req->execute(["id" => $id]);
        
            $log = $req->fetch(PDO::FETCH_ASSOC);
            if (!$log) {
                throw new HttpException("Log not found", 404);
            }
        
            return $this->formatLogWithUsername($log);
        } catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieves all logs, optionally limited by a specified number.
     * 
     * @param array|null $filters Optional array of filters to apply to the query. Can include conditions for any user fields.
     * @return array An array of log data as associative arrays
     * @author Mathieu Chauvet
     */
    public function getAll(?array $filters = null) {
        try {
            $query = "SELECT l.*, u.username, p.name as product_name, p.sku as product_sku
                    FROM {$this->table} l 
                    LEFT JOIN users u ON l.user_id = u.id
                    LEFT JOIN products p ON l.product_id = p.id";
            
            $filterData = $this->buildFilterConditions($filters, 'l');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }
            
            $query .= " ORDER BY l.created_at DESC" . $filterData['limit'];
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'formatLogWithUsername'], $logs);
        }  catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieves the most recently added log.
     * 
     * @return array|stdClass The log data as an associative array, or an empty object if no categories are found
     * @author Rémis Rubis
     */
    public function getLastLog() {
        try {
            $query = "SELECT l.*, u.username, p.name as product_name, p.sku as product_sku
                    FROM {$this->table} l 
                    LEFT JOIN users u ON l.user_id = u.id
                    LEFT JOIN products p ON l.product_id = p.id 
                    ORDER BY l.id DESC LIMIT 1";

            $req = $this->db->prepare($query);
            $req->execute();
        
            if ($req->rowCount() > 0) {
                $log = $req->fetch(PDO::FETCH_ASSOC);
                return $this->formatLogWithUsername($log);
            }
            
            return new stdClass();
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }


    // ┌────────────────────────────────┐
    // | -------- CORE METHODS -------- |
    // └────────────────────────────────┘

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
    ) {
        $this->validateChangeType($changeType);

        // Validate user_id if provided
        if ($userId !== null && $userId <= 0) {
            throw new HttpException("Invalid user ID", 400);
        }

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


    // ┌──────────────────────────────────┐
    // | -------- HELPER METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Get the description for a given change type
     *
     * @param string $type The type of change to get description for
     * @return string|null The description of the change type, or null if type is invalid
     * @author Mathieu Chauvet
     */
    public function getChangeTypeDescription(string $type) {
        return $this->validChangeTypes[$type] ?? null;
    }

    /**
     * Validate that the given change type is allowed
     *
     * @param string $type The type of change to validate
     * @throws HttpException If the change type is invalid
     * @return void
     * @author Mathieu Chauvet
     */
    private function validateChangeType(string $type) {
        if (!array_key_exists($type, $this->validChangeTypes)) {
            throw new HttpException(
                "Invalid change type. Must be one of: " . implode(', ', array_keys($this->validChangeTypes)), 
                400
            );
        }
    }

    /**
     * Formats a log entry by replacing the user_id with the corresponding username
     * 
     * @param array $log The log entry to format
     * @return array The formatted log with username instead of user_id
     * @throws HttpException If there's a database error
     * @author Mathieu Chauvet
     */
    private function formatLogWithUsername(array $log) {
        if ($log['user_id']) {
            $log['username'] = $log['username'] ?? 'Unknown User';
        } else {
            $log['username'] = 'System';
        }
        unset($log['user_id']);

        if (!$log['product_name']) {
            $log['product_name'] = 'Deleted Product';
            $log['product_sku'] = 'N/A';
        }
        unset($log['product_id']);

        return $log;
    }
}