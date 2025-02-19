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

    #[Route("DELETE", "/users/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])]
    public function deleteUser() {
        return $this->user->delete(intval($this->params['id']));
    }

    #[Route("GET", "/users/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])] 
    public function getUser() {
        return $this->user->get(intval($this->params['id']));
    }

    #[Route("GET", "/users", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])]
    public function getUsers() {
            $limit = isset($this->params['limit']) ? intval($this->params['limit']) : null;
            return $this->user->getAll($limit);
    }

    #[Route("PATCH", "/users/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin'])]
    public function updateUser() {
        try {
            $id = intval($this->params['id']);
            $data = $this->body;

            # Check if the data is empty
            if (empty($data)) {
                throw new HttpException("Missing parameters for the update.", 400);
            }

            # Check for missing fields
            $missingFields = array_diff($this->user->authorized_fields_to_update, array_keys($data));
            if (!empty($missingFields)) {
                throw new HttpException("Missing fields: " . implode(", ", $missingFields), 400);
            }

            $this->user->update($data, intval($id));

            # Let's return the updated user
            return $this->user->get($id);
        } catch (HttpException $e) {
            throw $e;
        }
    }

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
}
