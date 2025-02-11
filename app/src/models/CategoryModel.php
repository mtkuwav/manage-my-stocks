<?php

namespace App\Models;

use App\Models\SqlConnect;
use \stdClass;
use \PDO;

class CategoryModel extends SqlConnect {
    private string $table = "categories";

    public function getById(int $id) {
        $req = $this->db->prepare("SELECT * FROM $this->table WHERE id = :id");
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
}