<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\UserModel;
use App\Utils\Route;
use App\Utils\HttpException;
use App\Middlewares\AuthMiddleware;

class User extends Controller {
    protected object $user;

    public function __construct($param) {
        $this->user = new UserModel();

        parent::__construct($param);
    }


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get a specific user by ID.
     *
     * @return array The user data
     * @throws HttpException if user not found
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("GET", "/users/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])] 
    public function getUser() {
        try {
            $id = intval($this->params['id']);
            $user = $this->user->get($id);
            
            if (empty($user)) {
                throw new HttpException("User not found", 404);
            }
            
            return $user;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Get all users with optional limit.
     * 
     * @param int|null $limit Optional parameter to limit the number of returned users
     * @return array Array of user records
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("GET", "/users", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])]
    public function getUsers() {
        try {
            $limit = isset($this->params['limit']) ? intval($this->params['limit']) : null;

            if ($limit !== null && $limit <= 0) {
                throw new HttpException("Limit must be a positive number", 400);
            }

            $users = $this->user->getAll($limit);

            if (empty($users)) {
                return [];
            }

            return $users;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Update user information.
     *
     * @throws HttpException if the request body is empty or contains no valid fields
     * @return array The updated user data
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("PATCH", "/users/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])]
    public function updateUser() {
        try {
            $id = intval($this->params['id']);
            $data = $this->body;

            # Check if the data is empty
            if (empty($data)) {
                throw new HttpException("Missing parameters for the update.", 400);
            }

            # Check for valid fields
            $allowedFields = array_intersect_key($data, array_flip($this->user->authorized_fields_to_update));
            if (empty($allowedFields)) {  // Changed from !empty to empty
                throw new HttpException("No valid fields to update.", 400);
            }

            $this->user->update($allowedFields, intval($id));

            # Let's return the updated user
            return $this->user->get($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Promotes a user to admin role.
     *
     * @throws HttpException if promotion fails
     * @return array The updated user data after promotion
     * @author Mathieu Chauvet
     */
    #[Route("POST", "/users/:id/promote", middlewares: [AuthMiddleware::class], allowedRoles: ['admin'])]
    public function promoteUser() {
        try {
            $id = intval($this->params['id']);
            return $this->user->promoteToAdmin($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Update the password of an user.
     *
     * @throws HttpException if the new password is missing or update fails
     * @return array containing success message
     * @author Mathieu Chauvet
     */
    #[Route("PATCH", "/users/:id/update-password", middlewares: [AuthMiddleware::class], allowedRoles: ['admin'])]
    public function updateUserPassword() {
        try {
            $id = intval($this->params['id']);
            $data = $this->body;

            if (empty($data['new_password'])) {
                    throw new HttpException("New password is required.", 400);
            }

            $this->user->updatePassword($id, $data['new_password']);
            return ["message" => "Password updated successfully"];
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- DELETE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Delete a user by ID.
     *
     * @return array The deletion result
     * @throws HttpException if deletion fails
     * @author Rémis Rubis, Mathieu Chauvet
     */
    #[Route("DELETE", "/users/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])]
    public function deleteUser() {
        try {
            $id = intval($this->params['id']);

            if (!$this->user->get($id)) {
                throw new HttpException("User not found", 404);
            }

            return $this->user->delete($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }
}
