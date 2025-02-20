<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\HttpException;
use \stdClass;
use \PDO;

class CategoryModel extends SqlConnect {
    private string $table = "categories";
    public $authorized_fields_to_update = ['name'];


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Create a new category in the system.
     *
     * @param array $data An associative array containing 'name'
     * @return array An associative array containing the ID and the name of the category that was just added.
     * @throws HttpException If the category already exists.
     * @author  Mathieu Chauvet
     */
    public function createCategory(array $data) {
        $query = "SELECT name FROM $this->table WHERE name = :name";
        $req = $this->db->prepare($query);
        $req->execute(["name" => $data["name"]]);
        
        if ($req->rowCount() > 0) {
            throw new HttpException("category already exists!", 400);
        }

        // Create the category
        $query_add = "INSERT INTO $this->table (name)
                        VALUES (:name)";
        $req2 = $this->db->prepare($query_add);
        $req2->execute([
            "name" => $data["name"],
        ]);

        $categoryId = $this->db->lastInsertId();

        return [
            "id" => $categoryId,
            "name" => $data["name"]
        ];
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Retrieves a category by its ID.
     * 
     * @param int $id The ID of the category to retrieve
     * @return array The category data as an associative array
     * @author Mathieu Chauvet
     */
    public function getById(int $id) {
        if ($id <= 0) {
            throw new HttpException("Invalid category ID", 400);
        }
    
        $req = $this->db->prepare("SELECT * FROM categories WHERE id = :id");
        $req->execute(["id" => $id]);
    
        $category = $req->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            throw new HttpException("category not found", 404);
        }
    
        return $category;
    }

    /**
     * Retrieves all categories, optionally limited by a specified number.
     * 
     * @param int|null $limit The maximum number of categories to retrieve (optional)
     * @return array An array of category data as associative arrays
     * @author Mathieu Chauvet
     */
    public function getAll(?int $limit = null) {
        try {
            $query = "SELECT * FROM {$this->table}";
            
            if ($limit !== null) {
                if ($limit <= 0) {
                    throw new HttpException("Limit must be a positive number", 400);
                }
                $query .= " LIMIT :limit";
                $params = [':limit' => (int)$limit];
            } else {
                $params = [];
            }
            
            $req = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $req->bindValue($key, $value, PDO::PARAM_INT);
            }
            $req->execute();
            
            return $req->fetchAll(PDO::FETCH_ASSOC);

        }  catch (HttpException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new HttpException("Unexpected error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieves the most recently added category.
     * 
     * @return array|stdClass The category data as an associative array, or an empty object if no categories are found
     * @author Rémis Rubis
     */
    public function getLast() {
        $req = $this->db->prepare("SELECT * FROM $this->table ORDER BY id DESC LIMIT 1");
        $req->execute();
    
        return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Updates a category's information.
     * 
     * @param array $data The data to update, as an associative array
     * @param int $id The ID of the category to update
     * @return array|stdClass The updated category data as an associative array, or an empty object if the category is not found
     * @throws HttpException If no fields are provided for update or if the update fails
     * @author Mathieu Chauvet
     */
    public function update(array $data, int $id) {
        $request = "UPDATE $this->table SET ";
        $params = [];
        $fields = [];

        # Prepare the query dynamically based on the provided data
        foreach ($data as $key => $value) {
            if (in_array($key, $this->authorized_fields_to_update)) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        # Validate name if present
        if (isset($data['name']) && empty(trim($data['name']))) {
            throw new HttpException("Category name cannot be empty", 400);
        }

        # Check if there are any valid fields to update
        if (empty($fields)) {
            throw new HttpException("No valid fields to update. Allowed fields: " . implode(", ", $this->authorized_fields_to_update), 400);
        }

        $params[':id'] = $id;
        $query = $request . implode(", ", $fields) . " WHERE id = :id";

        $req = $this->db->prepare($query);
        $success = $req->execute($params);

        if (!$success) {
            throw new HttpException("Failed to update category", 500);
        }

        return $this->getById($id);
    }


    // ┌──────────────────────────────────┐
    // | -------- DELETE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Deletes a category by its ID.
     * 
     * @param int $id The ID of the category to delete
     * @return array An associative array containing a success message
     * @author Mathieu Chauvet
     */
    public function delete(int $id) {
        $category = $this->getById($id);
        if (empty((array)$category)) {
            throw new HttpException("category not found", 404);
        }

        $userReq = $this->db->prepare("DELETE FROM $this->table WHERE id = :id");
        $success = $userReq->execute(["id" => $id]);

        if (!$success) {
            throw new HttpException("Failed to delete category", 500);
        }

        return ["message" => "category successfully deleted"];
    }
}