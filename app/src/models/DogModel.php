<?php

namespace App\Models;

use App\Models\SqlConnect;
use \PDO;

class DogModel extends SqlConnect {
  private string $table = "dogs";

  public function getById(int $id) {
    $query = "SELECT * FROM $this->table WHERE id = :id";
    $req = $this->db->prepare($query);
    $req->execute(["id" => $id]);

    return $req->fetch(PDO::FETCH_ASSOC);  
  }

  public function create(array $data) {
    $query = "
      INSERT INTO $this->table (name) VALUES (:name)
    ";
    $req = $this->db->prepare($query);
    $req->execute($data);

    return $this->db->lastInsertId();
  }
}