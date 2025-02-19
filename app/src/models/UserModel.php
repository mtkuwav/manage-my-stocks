<?php

namespace App\Models;

use \PDO;
use stdClass;
use App\Utils\HttpException;

class UserModel extends SqlConnect {
  private $table = "users";
  public $authorized_fields_to_update = ['username', 'email', 'role'];
  private string $passwordSalt = 'sqidq7sà';

  /**
   * Deletes a user by their ID.
   * 
   * @param int $id The ID of the user to delete
   * @return stdClass An empty object
   * @author Rémis Rubis
   */
  public function delete(int $id) {
    $req = $this->db->prepare("DELETE FROM $this->table WHERE id = :id");
    $req->execute(["id" => $id]);
    return new stdClass();
  }

  /**
   * Retrieves a user by their ID.
   * 
   * @param int $id The ID of the user to retrieve
   * @return array|stdClass The user data as an associative array, or an empty object if the user is not found
   * @author Rémis Rubis
   */
  public function get(int $id) {
    $req = $this->db->prepare("SELECT * FROM users WHERE id = :id");
    $req->execute(["id" => $id]);

    return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
  }

  /**
   * Retrieves all users, optionally limited by a specified number.
   * 
   * @param int|null $limit The maximum number of users to retrieve (optional)
   * @return array An array of user data as associative arrays
   * @author Rémis Rubis
   */
  public function getAll(?int $limit = null) {
    $query = "SELECT * FROM {$this->table}";
    
    if ($limit !== null) {
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
  }

  /**
   * Retrieves the most recently added user.
   * 
   * @return array|stdClass The user data as an associative array, or an empty object if no users are found
   * @author Rémis Rubis
   */
  public function getLast() {
    $req = $this->db->prepare("SELECT * FROM $this->table ORDER BY id DESC LIMIT 1");
    $req->execute();

    return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
  }

  /**
   * Updates a user's information.
   * 
   * @param array $data The data to update, as an associative array
   * @param int $id The ID of the user to update
   * @return array|stdClass The updated user data as an associative array, or an empty object if the user is not found
   * @throws HttpException If no fields are provided for update or if the update fails
   * @author Rémis Rubis
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

    $params[':id'] = $id;
    $query = $request . implode(", ", $fields) . " WHERE id = :id";

    $req = $this->db->prepare($query);
    $req->execute($params);
    
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
      $query = "UPDATE $this->table SET role = 'admin' 
                WHERE id = :id AND role = 'manager'";
      $req = $this->db->prepare($query);
      $success = $req->execute(['id' => $userId]);
  
      if (!$success || $req->rowCount() === 0) {
          throw new HttpException("Failed to promote user or user is already admin", 400);
      }

      return $this->get($userId);
  }

  /**
     * Updates the password of a user.
     *
     * @param int $userId The ID of the user whose password is to be updated.
     * @param string $newPassword The new password.
     * @return bool True if the password was updated successfully, false otherwise.
     * @throws HttpException If the update fails.
     * @author Mathieu Chauvet
     */
    public function updatePassword(int $userId, string $newPassword) {
      $saltedPassword = $newPassword . $this->passwordSalt;
      $hashedPassword = password_hash($saltedPassword, PASSWORD_BCRYPT);

      $query = "UPDATE $this->table SET password_hash = :password_hash WHERE id = :id";
      $req = $this->db->prepare($query);
      $success = $req->execute([
          'password_hash' => $hashedPassword,
          'id' => $userId
      ]);

      if (!$success) {
          throw new HttpException("Failed to update password", 500);
      }

      return true;
  }
}