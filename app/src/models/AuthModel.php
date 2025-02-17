<?php

namespace App\Models;

use App\Models\SqlConnect;
use App\Utils\{HttpException, JWT};
use \PDO;

class AuthModel extends SqlConnect {
  private string $tableUsers  = "users";
  private string $tableRefresh = "refresh_tokens";
  private int $accessTokenValidity = 3600; // 1 hour
  private int $refreshTokenValidity = 604800; // 7 days
  private string $passwordSalt = "sqidq7sà";
  
  /**
   * Registers a new user in the system.
   *
   * @param array $data An associative array containing 'username', 'email', 'password', and optionally 'role'.
   * @return array An associative array containing a JWT token.
   * @throws HttpException If the user already exists.
   * @author Rémis Rubis, Mathieu Chauvet
   */
  public function register(array $data) {
    $query = "SELECT email FROM $this->tableUsers WHERE email = :email";
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
    $query_add = "INSERT INTO $this->tableUsers (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)";
    $req2 = $this->db->prepare($query_add);
    $req2->execute([
      "username" => $data["username"],
      "email" => $data["email"],
      "password_hash" => $hashedPassword,
      "role" => $data["role"] ?? 'manager'
    ]);

    $id = $this->db->lastInsertId();

    // Generate the JWT token
    $token = $this->generateJWT($id, $role);

    return ['token' => $token];
  }

  /**
   * Logs a user into the system.
   *
   * @param string $email The email of the user.
   * @param string $password The password of the user.
   * @return array An associative array containing a JWT token.
   * @throws \Exception If the credentials are invalid.
   * @author Rémis Rubis, Mathieu Chauvet
   */
  public function login($email, $password) {
    $query = "SELECT * FROM $this->tableUsers WHERE email = :email";
    $req = $this->db->prepare($query);
    $req->execute(['email' => $email]);

    $user = $req->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Combine input password with salt and verify
        $saltedPassword = $password . $this->passwordSalt;
        
        if (password_verify($saltedPassword, $user['password_hash'])) {
            $accessToken = $this->generateJWT($user['id'], $user['role']);
            $refreshToken = $this->generateRefreshToken($user['id']);
            return ['access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => $this->accessTokenValidity,
                    'token_type' => 'Bearer'
                  ];
        }
    }

    throw new \Exception("Invalid credentials.");
  }

  /**
   * Generates a JWT token.
   *
   * @param string $role The role of the user.
   * @return string The generated JWT token.
   * @author Rémis Rubis, Mathieu Chauvet
   */
  private function generateJWT(int $userId, string $role) {
    $payload = [
      'user_id' => $userId,
      'role' => $role,
      'exp' => time() + $this->accessTokenValidity
    ];
    return JWT::generate($payload);
  }

  /**
   * Generates a refresh token for a user and stores it in the database.
   *
   * @param int $userId The ID of the user.
   * @return string The generated refresh token.
   * @author Mathieu Chauvet
   */
  private function generateRefreshToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + $this->refreshTokenValidity);

    $query = "INSERT INTO refresh_tokens (token, user_id, expires_at) 
              VALUES (:token, :user_id, :expires_at)";
    $req = $this->db->prepare($query);
    $req->execute([
        'token' => $token,
        'user_id' => $userId,
        'expires_at' => $expiresAt
    ]);

    return $token;
}
}