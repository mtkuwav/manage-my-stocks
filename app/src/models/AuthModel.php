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
    private int $maxActiveSessions = 5;
    
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
        $role = 'manager';

        // Create the user
        $query_add = "INSERT INTO $this->tableUsers (username, email, password_hash, role)
                        VALUES (:username, :email, :password_hash, :role)";
        $req2 = $this->db->prepare($query_add);
        $req2->execute([
            "username" => $data["username"],
            "email" => $data["email"],
            "password_hash" => $hashedPassword,
            "role" => $role
        ]);

        $userId = $this->db->lastInsertId();

        // Generate the JWT token and the refresh token
        $accessToken = $this->generateJWT($userId, $role);
        $refreshToken = $this->generateRefreshToken($userId);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->accessTokenValidity,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Authenticates a user and generates access and refresh tokens.
     *
     * @param string $email The user's email address
     * @param string $password The user's password
     * @return array An array containing access_token, refresh_token, expires_in, and token_type
     * @throws \Exception If the credentials are invalid
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
                
                $this->cleanupOldSessions($user['id']);

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

    /**
     * Retrieves the most recent valid refresh token for a user.
     *
     * @param int $userId The ID of the user.
     * @return array|false The refresh token data as an associative array, or false if no valid token exists.
     * @author Mathieu Chauvet
     */
    private function getValidRefreshToken($userId) {
        $query = "SELECT * FROM refresh_tokens 
                WHERE user_id = :user_id 
                AND expires_at > NOW() 
                AND revoked = 0 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $req = $this->db->prepare($query);
        $req->execute(['user_id' => $userId]);
        return $req->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cleans up old sessions by revoking refresh tokens when the maximum number of active sessions is exceeded.
     *
     * @param int $userId The ID of the user whose sessions need to be cleaned up.
     * @return void
     * @author Mathieu Chauvet
     */
    private function cleanupOldSessions($userId) {
        // Compter les sessions actives
        $query = "SELECT COUNT(*) FROM refresh_tokens 
                WHERE user_id = :user_id 
                AND expires_at > NOW() 
                AND revoked = 0";
        
        $req = $this->db->prepare($query);
        $req->execute(['user_id' => $userId]);
        $count = $req->fetchColumn();

        if ($count >= $this->maxActiveSessions) {
            // Révoquer les plus anciens tokens
            $query = "UPDATE refresh_tokens 
                    SET revoked = 1 
                    WHERE user_id = :user_id 
                    AND revoked = 0 
                    ORDER BY created_at ASC 
                    LIMIT " . ($count - $this->maxActiveSessions + 1);
            
            $req = $this->db->prepare($query);
            $req->execute(['user_id' => $userId]);
        }
    }
    
    /**
     * Revokes a refresh token
     * 
     * @param string $refreshToken The refresh token to revoke
     * @param int $userId The user ID for additional security
     * @return bool True if token was revoked, false otherwise
     * @author Mathieu Chauvet
     */
    public function revokeToken($refreshToken, $userId) {
        $query = "UPDATE $this->tableRefresh 
                SET revoked = 1 
                WHERE token = :token 
                AND user_id = :user_id";

        $req = $this->db->prepare($query);
        return $req->execute([
            'token' => $refreshToken,
            'user_id' => $userId
        ]);
    }

    /**
     * Revokes all refresh tokens for a user
     * 
     * @param int $userId The user ID
     * @return bool True if tokens were revoked, false otherwise
     */
    public function revokeAllTokens($userId) {
        $query = "UPDATE $this->tableRefresh 
                SET revoked = 1 
                WHERE user_id = :user_id 
                AND revoked = 0";

        $req = $this->db->prepare($query);
        return $req->execute(['user_id' => $userId]);
    }

    /**
     * Generates a new access token using a valid refresh token.
     *
     * @param string $refreshToken The refresh token to validate
     * @return array An array containing the new access token, expiration time, and token type
     * @throws HttpException If the refresh token is invalid or expired
     * @author Mathieu Chauvet
     */
    public function refreshAccessToken(string $refreshToken) {
        // Vérifier si le refresh token existe et est valide
        $query = "SELECT rt.*, u.role FROM refresh_tokens rt 
                JOIN users u ON rt.user_id = u.id 
                WHERE rt.token = :token 
                AND rt.expires_at > NOW() 
                AND rt.revoked = 0";

        $req = $this->db->prepare($query);
        $req->execute(['token' => $refreshToken]);
        $tokenData = $req->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            throw new HttpException("Invalid or expired refresh token", 401);
        }

        // Générer un nouveau access token
        $accessToken = $this->generateJWT($tokenData['user_id'], $tokenData['role']);

        return [
            'access_token' => $accessToken,
            'expires_in' => $this->accessTokenValidity,
            'token_type' => 'Bearer'
        ];
    }
}