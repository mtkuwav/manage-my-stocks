<?php

namespace App\Models;

use \PDO;

class SqlConnect {
  public object $db;
  private string $host;
  private string $port;
  private string $dbname;
  private string $password;
  private string $user;

  public function __construct() {
    $this->host = getenv('DB_HOST');
    $this->dbname = getenv('DB_NAME');
    $this->user = getenv('DB_USER');
    $this->password = getenv('DB_PASSWORD');
    $this->port = getenv('DB_PORT');

    $dsn = 'mysql:host='.$this->host.';port='.$this->port.';dbname='.$this->dbname;
    $this->db = new PDO(
      $dsn,
      $this->user,
      $this->password
    );

    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(PDO::ATTR_PERSISTENT, false);
  }

  public function transformDataInDot($data) {
    $dataFormated = [];

    foreach ($data as $key => $value) {
      $dataFormated[':' . $key] = $value;
    }

    return $dataFormated;
  }
}