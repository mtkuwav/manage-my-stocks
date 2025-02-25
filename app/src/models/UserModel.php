<?php

namespace App\Models;

use \PDO;
use stdClass;
use App\Utils\HttpException;
use App\Traits\FilterableTrait;

class UserModel extends SqlConnect {
    use FilterableTrait;

    private string $userTable = 'users';
    private string $refreshTokenTable = 'refresh_tokens';
    public $authorized_fields_to_update = ['username', 'email', 'role'];
    private string $passwordSalt = 'sqidq7sà';


    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Retrieves a user by their ID.
     * 
     * @param int $id The ID of the user to retrieve
     * @return array The user data as an associative array
     * @author Rémis Rubis, Mathieu Chauvet
     */
    public function get(int $id) {
        if ($id <= 0) {
            throw new HttpException("Invalid user ID", 400);
        }
    
        $req = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $req->execute(["id" => $id]);
    
        $user = $req->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new HttpException("User not found", 404);
        }
    
        return $this->removeSensitiveData($user);
    }

    /**
     * Retrieves all users, optionally limited by a specified number.
     * 
     * @param array|null $filters Optional array of filters to apply to the query. Can include conditions for any user fields.
     * @return array An array of user data as associative arrays
     * @author Rémis Rubis, Mathieu Chauvet
     */
    public function getAll(?array $filters = null) {
        try {
            $query = "SELECT u.* FROM {$this->userTable} u";
            
            $filterData = $this->buildFilterConditions($filters, 'u');
            
            if (!empty($filterData['conditions'])) {
                $query .= " WHERE " . implode(" AND ", $filterData['conditions']);
            }

            $query .= " ORDER BY u.created_at DESC" . $filterData['limit'];
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($filterData['params']);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map([$this, 'removeSensitiveData'], $users);
        } catch (\PDOException $e) {
            throw new HttpException("Database error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieves the most recently added user.
     * 
     * @return array|stdClass The user data as an associative array, or an empty object if no users are found
     * @author Rémis Rubis
     */
    public function getLast() {
        $req = $this->db->prepare("SELECT * FROM $this->userTable ORDER BY id DESC LIMIT 1");
        $req->execute();

        return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Updates a user's information.
     * 
     * @param array $data The data to update, as an associative array
     * @param int $id The ID of the user to update
     * @return array|stdClass The updated user data as an associative array, or an empty object if the user is not found
     * @throws HttpException If no fields are provided for update or if the update fails
     * @author Rémis Rubis, Mathieu Chauvet
     */
    public function update(array $data, int $id) {
        $request = "UPDATE $this->userTable SET ";
        $params = [];
        $fields = [];

        # Prepare the query dynamically based on the provided data
        foreach ($data as $key => $value) {
            if (in_array($key, $this->authorized_fields_to_update)) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $value;
            }
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
            throw new HttpException("Failed to update user", 500);
        }

        return $this->get($id);
    }

    /**
     * Promotes a user from manager role to admin role.
     * 
     * @param int $userId The ID of the user to promote
     * @return array The updated user data
     * @throws HttpException If promotion fails or user is already admin
     * @author Mathieu Chauvet
     */
    public function promoteToAdmin(int $userId) {
        $query = "UPDATE $this->userTable SET role = 'admin' 
                            WHERE id = :id AND role = 'manager'";
        $req = $this->db->prepare($query);
        $success = $req->execute(['id' => $userId]);

        if (!$success || $req->rowCount() === 0) {
                throw new HttpException("Failed to promote user or user is already admin", 400);
        }

        return $this->get($userId);
    }


    // ┌──────────────────────────────────┐
    // | -------- DELETE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Deletes a user by their ID.
     * 
     * @param int $id The ID of the user to delete
     * @return array An associative array containing a success message
     * @author Mathieu Chauvet
     */
    public function delete(int $id) {
        $user = $this->get($id);
        if (empty((array)$user)) {
            throw new HttpException("User not found", 404);
        }

        $tokenReq = $this->db->prepare("DELETE FROM $this->refreshTokenTable WHERE user_id = :id");
        $tokenReq->execute(["id" => $id]);

        $userReq = $this->db->prepare("DELETE FROM $this->userTable WHERE id = :id");
        $success = $userReq->execute(["id" => $id]);

        if (!$success) {
            throw new HttpException("Failed to delete user", 500);
        }

        return ["message" => "User successfully deleted"];
    }


    // ┌────────────────────────────────┐
    // | -------- MISC METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Removes sensitive data (password hash) from user data.
     * 
     * @param array $user The user data array containing sensitive information
     * @return array The user data array with sensitive information removed
     * @author Mathieu Chauvet
     */
    private function removeSensitiveData(array $user) {
        unset($user['password_hash']);
        return $user;
    }
}