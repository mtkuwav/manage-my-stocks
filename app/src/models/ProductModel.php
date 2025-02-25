<?php

namespace App\Models;

use App\Models\{SqlConnect, InventoryLogModel};
use App\Utils\{HttpException, JWT};
use App\Traits\{StockManagementTrait, FilterableTrait};
use \stdClass;
use \PDO;

class ProductModel extends SqlConnect {
    use StockManagementTrait;
    use FilterableTrait;

    private InventoryLogModel $inventoryLog;
    
    private string $tableProducts = "products";
    public $authorized_fields_to_update = ['name', 'description', 'price', 'quantity_in_stock'];

    public function __construct() {
        parent::__construct();
        $this->inventoryLog = new InventoryLogModel();
    }


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new product in the system.
     *
     * @param array $data Product data containing name, description, sku, price, quantity_in_stock, category_id
     * @return array The created product data
     * @throws HttpException If the product already exists or required fields are missing
     * @author Mathieu Chauvet
     */
    public function createProduct(array $data) {
        try {
            // Validations avant transaction
            $requiredFields = ['name', 'price', 'category_id'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new HttpException("$field is required", 400);
                }
            }

            // Validate category and get its prefix
            $categoryCheck = $this->db->prepare(
                "SELECT c.id, c.name 
                FROM categories c 
                WHERE c.id = :id"
            );
            $categoryCheck->execute(["id" => $data["category_id"]]);
            $category = $categoryCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new HttpException("Category not found", 404);
            }

            // Generate clean category prefix (prevent empty prefix)
            $categoryName = trim($category['name']);
            if (empty($categoryName)) {
                throw new HttpException("Invalid category name", 400);
            }
            
            $categoryPrefix = substr(strtoupper($categoryName), 0, 4);
            if (empty($categoryPrefix)) {
                throw new HttpException("Could not generate valid SKU prefix from category", 400);
            }

            // Check for duplicate product name in the same category
            $duplicateCheck = $this->db->prepare(
                "SELECT id FROM $this->tableProducts 
                WHERE LOWER(name) = LOWER(:name) 
                AND category_id = :category_id"
            );
            $duplicateCheck->execute([
                "name" => $data["name"],
                "category_id" => $data["category_id"]
            ]);

            if ($duplicateCheck->rowCount() > 0) {
                throw new HttpException(
                    "A product with this name already exists in this category ! You may want to increase stock ?", 400);
            }

            // Prepare all data before transaction
            $data['sku'] = $this->generateSKU($categoryPrefix, $data['name']);
            $data['quantity_in_stock'] = $data['quantity_in_stock'] ?? 0;
            $data['description'] = $data['description'] ?? null;

            if (isset($data['quantity_in_stock'])) {
                $this->validateStock($data['quantity_in_stock']);
            }

            // Create product
            $query = "INSERT INTO $this->tableProducts 
                    (name, description, sku, price, quantity_in_stock, category_id)
                    VALUES (:name, :description, :sku, :price, :quantity_in_stock, :category_id)";
            
            $stmt = $this->db->prepare($query);
            $success = $stmt->execute([
                "name" => $data["name"],
                "description" => $data["description"],
                "sku" => $data["sku"],
                "price" => $data["price"],
                "quantity_in_stock" => $data["quantity_in_stock"],
                "category_id" => $data["category_id"]
            ]);

            if (!$success) {
                throw new HttpException("Failed to create product", 500);
            }

            $productId = $this->db->lastInsertId();

            // Get user_id from JWT token
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $token = str_replace('Bearer ', '', $token);
            $payload = JWT::decryptToken($token);
            $userId = $payload['user_id'] ?? null;

            // Log inventory change with user_id
            $this->inventoryLog->logChange(
                $productId,
                $userId,  // Ajout de l'user_id
                0,
                $data['quantity_in_stock'],
                'initial'
            );

            return $this->getById($productId);

        } catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Failed to create product: " . $e->getMessage(), 500);
        }
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Retrieves a product by its ID.
     * 
     * @param int $id The ID of the product to retrieve
     * @return array The product data as an associative array
     * @author Mathieu Chauvet
     */
    public function getById(int $id) {
        try {
            if ($id <= 0) {
                throw new HttpException("Invalid product ID", 400);
            }
        
            $req = $this->db->prepare("SELECT * FROM $this->tableProducts WHERE id = :id");
            $req->execute(["id" => $id]);
        
            $product = $req->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new HttpException("Product not found", 404);
            }
        
            return $product;
        } catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieves all products, optionally limited by a specified number.
     * 
     * @param array|null $filters Optional array of filters to apply to the query. Can include conditions for any user fields.
     * @return array An array of product data as associative arrays
     * @author Mathieu Chauvet
     */
    public function getAll(?array $filters = null) {
        try {
            $query = "SELECT p.* FROM {$this->tableProducts} p";
            
            $filterData = $this->buildFilterConditions($filters, 'p');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }
            
            $query .= " ORDER BY p.created_at DESC" . $filterData['limit'];
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }  catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieves the most recently added product.
     * 
     * @return array|stdClass The product data as an associative array, or an empty object if no categories are found
     * @author Rémis Rubis
     */
    public function getLast() {
        $req = $this->db->prepare("SELECT * FROM $this->tableProducts ORDER BY id DESC LIMIT 1");
        $req->execute();
    
        return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Updates a product's information.
     * 
     * @param array $data The data to update, as an associative array
     * @param int $id The ID of the product to update
     * @return array|stdClass The updated product data as an associative array, or an empty object if the product is not found
     * @throws HttpException If no fields are provided for update or if the update fails
     * @author Mathieu Chauvet
     */
    public function update(array $data, int $id) {
        try {
            $currentProduct = $this->getById($id);

            $fields = [];
            $params = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $this->authorized_fields_to_update)) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (empty($fields)) {
                throw new HttpException(
                    "No valid fields to update. Allowed fields: " . implode(", ", $this->authorized_fields_to_update), 
                    400
                );
            }

            if (isset($data['name']) && empty(trim($data['name']))) {
                throw new HttpException("Product name cannot be empty", 400);
            }

            if (isset($data['quantity_in_stock'])) {
                $this->validateStock($data['quantity_in_stock']);
            }

            if (isset($data['price'])) {
                $this->validatePrice($data['price']);
            }

            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            try {
                $query = "UPDATE $this->tableProducts SET " . implode(", ", $fields) . " WHERE id = :id";
                $params[':id'] = $id;
                
                $stmt = $this->db->prepare($query);
                $stmt->execute($params);

                if (isset($data['quantity_in_stock']) && $data['quantity_in_stock'] != $currentProduct['quantity_in_stock']) {
                    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                    $token = str_replace('Bearer ', '', $token);
                    $payload = JWT::decryptToken($token);
                    
                    $this->inventoryLog->logChange(
                        $id,
                        $payload['user_id'] ?? null,
                        $currentProduct['quantity_in_stock'],
                        $data['quantity_in_stock'],
                        'adjustment'
                    );
                }

                return $this->getById($id);

            } catch (\Exception $e) {
                throw $e;
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- DELETE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Deletes a product by its ID and handles related records.
     * 
     * @param int $id The ID of the product to delete
     * @return array An associative array containing a success message
     * @throws HttpException If the product is not found or deletion fails
     * @author Mathieu Chauvet
     */
    public function delete(int $id): bool {
        try {
            $product = $this->getById($id);
            
            try {
                $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                $token = str_replace('Bearer ', '', $token);
                $payload = JWT::decryptToken($token);

                $this->inventoryLog->logChange(
                    $id,
                    $payload['user_id'] ?? null,
                    $product['quantity_in_stock'],
                    0,
                    'deletion'
                );

                $stmt = $this->db->prepare("DELETE FROM $this->tableProducts WHERE id = :id");
                $success = $stmt->execute(['id' => $id]);
                
                if (!$success) {
                    throw new HttpException("Failed to delete product", 500);
                }

                return true;

            } catch (\Exception $e) {
                throw $e;
            }
        } catch (HttpException $e) {
            throw $e;
        }
    }
}