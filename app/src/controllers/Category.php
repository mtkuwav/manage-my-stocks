<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\CategoryModel;
use App\Utils\Route;
use App\Utils\HttpException;
use App\Middlewares\AuthMiddleware;

class Category extends Controller {
    protected object $category;

    public function __construct($params) {
        $this->category = new CategoryModel();

        parent::__construct($params);
    }

    /**
     * Delete a category by ID.
     *
     * @return array The deletion result
     * @throws HttpException if deletion fails
     * @author Mathieu Chauvet
     */
    #[Route("DELETE", "/categories/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function deleteCategory() {
        try {
            $id = intval($this->params['id']);

            if (!$this->category->getById($id)) {
                throw new HttpException("Category not found", 404);
            }

            return $this->category->delete($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Get a specific category by ID.
     *
     * @return array The category data
     * @throws HttpException if category not found
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("GET", "/categories/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])] 
    public function getById() {
        try {
            $id = intval($this->params['id']);
            $category = $this->category->getById($id);
            
            if (empty($category)) {
                throw new HttpException("Category not found", 404);
            }
            
            return $category;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Get all categories with optional limit.
     * 
     * @param int|null $limit Optional parameter to limit the number of returned categories
     * @return array Array of category records
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("GET", "/categories", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function getAll() {
        try {
            $limit = isset($this->params['limit']) ? intval($this->params['limit']) : null;

            if ($limit !== null && $limit <= 0) {
                throw new HttpException("Limit must be a positive number", 400);
            }

            $categories = $this->category->getAll($limit);

            if (empty($categories)) {
                return [];
            }

            return $categories;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Update category information.
     *
     * @throws HttpException if the request body is empty or contains no valid fields
     * @return array The updated category data
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("PATCH", "/categories/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function updateCategory() {
        try {
            $id = intval($this->params['id']);
            $data = $this->body;

            # Check if the data is empty
            if (empty($data)) {
                throw new HttpException("Missing parameters for the update.", 400);
            }

            # Check for valid fields
            $allowedFields = array_intersect_key($data, array_flip($this->category->authorized_fields_to_update));
            if (empty($allowedFields)) {
                throw new HttpException("No valid fields to update.", 400);
            }

            $this->category->update($allowedFields, intval($id));

            # Let's return the updated category
            return $this->category->getById($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Create a new category.
     *
     * @return array The newly created category data
     * @throws HttpException if creation fails or required fields are missing
     * @author Mathieu Chauvet
     */
    #[Route("POST", "/categories", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function createCategory() {
        try {
            $data = $this->body;

            if (empty($data['name'])) {
                throw new HttpException("Name is required for category creation", 400);
            }

            return $this->category->createCategory($data);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }
}