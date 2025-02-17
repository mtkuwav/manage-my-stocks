<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\{HttpException, JWT};
use \PDO;

class AuthModel extends SqlConnect {
  private string $table  = "users";
  private int $tokenValidity = 3600;
  private string $passwordSalt = "sqidq7sà";
  
  /**
   * Registers a new user in the system.
   *
   * @param array $data An associative array containing 'username', 'email', 'password', and optionally 'role'.
   * @return array An associative array containing a JWT token.
   * @throws HttpException If the user already exists.
   * @author Rémis Rubis
   */
  public function register(array $data) {
    $query = "SELECT email FROM $this->table WHERE email = :email";
    $req = $this->db->prepare($query);
    $req->execute(["email" => $data["email"]]);
    
    if ($req->rowCount() > 0) {
      throw new HttpException("User already exists!", 400);
    }

    // Combine password with salt and hash it
    $saltedPassword = $data["password"] . $this->passwordSalt;
    $hashedPassword = password_hash($saltedPassword, PASSWORD_BCRYPT);

    // Stock role into a variable to avoid making several requests for JWT token
    $role = $data["role"] ?? 'manager';

    // Create the user
    $query_add = "INSERT INTO $this->table (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)";
    $req2 = $this->db->prepare($query_add);
    $req2->execute([
      "username" => $data["username"],
      "email" => $data["email"],
      "password_hash" => $hashedPassword,
      "role" => $data["role"] ?? 'manager'
    ]);

    // Generate the JWT token
    $token = $this->generateJWT($role);

    return ['token' => $token];
  }

  /**
   * Logs a user into the system.
   *
   * @param string $email The email of the user.
   * @param string $password The password of the user.
   * @return array An associative array containing a JWT token.
   * @throws \Exception If the credentials are invalid.
   * @author Rémis Rubis
   */
  public function login($email, $password) {
    $query = "SELECT * FROM $this->table WHERE email = :email";
    $req = $this->db->prepare($query);
    $req->execute(['email' => $email]);

    $user = $req->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Combine input password with salt and verify
        $saltedPassword = $password . $this->passwordSalt;
        
        if (password_verify($saltedPassword, $user['password_hash'])) {
            $token = $this->generateJWT($user['role']);
            return ['token' => $token];
        }
    }

    throw new \Exception("Invalid credentials.");
  }

  /**
   * Generates a JWT token.
   *
   * @param string $role The role of the user.
   * @return string The generated JWT token.
   * @author Rémis Rubis
   */
  private function generateJWT(string $role) {
    $payload = [
      'role' => $role,
      'exp' => time() + $this->tokenValidity
    ];
    return JWT::generate($payload);
  }
}