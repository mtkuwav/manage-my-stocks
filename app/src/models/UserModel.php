<?php

namespace App\Models;

use \PDO;
use stdClass;
use App\Utils\HttpException;

class UserModel extends SqlConnect {
  private $table = "users";
  public $authorized_fields_to_update = ['username', 'email', 'role'];


  public function delete(int $id) {
    $req = $this->db->prepare("DELETE FROM $this->table WHERE id = :id");
    $req->execute(["id" => $id]);
    return new stdClass();
  }

  public function get(int $id) {
    $req = $this->db->prepare("SELECT * FROM users WHERE id = :id");
    $req->execute(["id" => $id]);

    return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
  }

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

  public function getLast() {
    $req = $this->db->prepare("SELECT * FROM $this->table ORDER BY id DESC LIMIT 1");
    $req->execute();

    return $req->rowCount() > 0 ? $req->fetch(PDO::FETCH_ASSOC) : new stdClass();
  }

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
}