<?php 

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\AuthModel;
use App\Utils\{Route, HttpException, JWT};
use App\Middlewares\AuthMiddleware;

class Auth extends Controller {
    protected object $auth;

    public function __construct($params) {
        $this->auth = new AuthModel();
        parent::__construct($params);
    }


    // ┌──────────────────────────────────┐
    // | -------- CREATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Register a new user with email, password and username
     * 
     * @throws HttpException if email, password or username is missing
     * @throws HttpException if registration fails
     * @return array containing the created user data
     * @author Rémis Rubis
     */
    #[Route("POST", "/auth/register")]
    public function register() {
        try {
            $data = $this->body;
            if (empty($data['email']) || empty($data['password']) || empty($data['username'])) {
                throw new HttpException("Missing email, username or password.", 400);
            }
            $user = $this->auth->register($data);
            return $user;
        } catch (HttpException $e) {
            throw new HttpException($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }


    // ┌──────────────────────────────────┐
    // | -------- UPDATE METHODS -------- |
    // └──────────────────────────────────┘

    /**
     * Update the password of the authenticated user.
     *
     * @throws HttpException if the new password is missing or update fails
     * @return array containing success message
     * @author Mathieu Chauvet
     */
    #[Route("PATCH", "/auth/update-password", middlewares: [AuthMiddleware::class], allowedRoles: ['admin', 'manager'])]
    public function updateUserPassword() {
        try {
            $data = $this->body;

            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $token = str_replace('Bearer ', '', $token);
            $payload = JWT::decryptToken($token);
            $userId = $payload['user_id'] ?? null;

            if (!$userId) {
                throw new HttpException("Authentication required", 401);
            }

            if (empty($data['current_password'])) {
                throw new HttpException("Current password is required", 400);
            }

            if (!$this->auth->verifyPassword($userId, $data['current_password'])) {
                throw new HttpException("Current password is incorrect", 401);
            }

            if ($data['current_password'] === $data['new_password']) {
                throw new HttpException("New password must be different from current password", 400);
            }

            if (empty($data['new_password'])) {
                    throw new HttpException("New password is required.", 400);
            }

            $this->auth->updatePassword($userId, $data['new_password']);
            return ["message" => "Password updated successfully"];
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }


    // ┌────────────────────────────────┐
    // | -------- AUTH METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Authenticate a user and generate access and refresh tokens
     * 
     * @throws HttpException if email or password is missing
     * @throws HttpException if authentication fails
     * @return array containing access_token and refresh_token
     * @author Rémis Rubis
     */
    #[Route("POST", "/auth/login")]
    public function login() {
        try {
            $data = $this->body;
            if (empty($data['email']) || empty($data['password'])) {
                throw new HttpException("Missing email or password.", 400);
            }
            $tokens = $this->auth->login($data['email'], $data['password']);
            return $tokens;
        } catch (HttpException $e) {
            throw new HttpException($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Logout the authenticated user by revoking their refresh token
     * 
     * @throws HttpException if refresh token is missing
     * @throws HttpException if logout fails
     * @return array containing success message
     * @author Mathieu Chauvet
     */
    #[Route("DELETE", "/auth/logout", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function logout() {
        try {
            $data = $this->body;
            if (empty($data['refresh_token'])) {
                throw new HttpException("Refresh token is required", 400);
            }
    
            $authHeader = getallheaders()['Authorization'];
            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                throw new HttpException("Invalid Authorization header format", 401);
            }
    
            $jwt = $matches[1];
            $payload = JWT::decryptToken($jwt);
            
            if (!isset($payload['user_id'])) {
                throw new HttpException("Invalid token payload", 401);
            }
    
            if ($this->auth->revokeToken($data['refresh_token'], $payload['user_id'])) {
                return ["message" => "Successfully logged out"];
            }
            
            throw new HttpException("Failed to logout", 500);
        } catch (HttpException $e) {
            throw new HttpException($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Revoke all refresh tokens for the authenticated user
     * 
     * @throws HttpException if revocation fails
     * @return array containing success message
     * @author Mathieu Chauvet
     */
    #[Route("DELETE", "/auth/logout/all", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function logoutAll() {
        try {
            $authHeader = getallheaders()['Authorization'];
            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                throw new HttpException("Invalid Authorization header format", 401);
            }
    
            $jwt = $matches[1];
            $payload = JWT::decryptToken($jwt);
            
            if (!isset($payload['user_id'])) {
                throw new HttpException("Invalid token payload", 401);
            }
    
            if ($this->auth->revokeAllTokens($payload['user_id'])) {
                return ["message" => "Successfully logged out from all sessions"];
            }
    
            throw new HttpException("Failed to logout", 500);
        } catch (HttpException $e) {
            throw new HttpException($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Regenerate an access token using a valid refresh token
     * 
     * @throws HttpException if refresh token is missing or invalid
     * @return array containing new access token
     * @author Mathieu Chauvet
     */
    #[Route("PUT", "/auth/refresh")]
    public function refresh() {
        try {
            $data = $this->body;
            if (empty($data['refresh_token'])) {
                throw new HttpException("Refresh token is required", 400);
            }

            $tokens = $this->auth->refreshAccessToken($data['refresh_token']);
            return $tokens;

        } catch (HttpException $e) {
            throw new HttpException($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }
}