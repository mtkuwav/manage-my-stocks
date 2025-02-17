<?php 

namespace App\Middlewares;

use App\Utils\JWT;

class AuthMiddleware {
    private array $allowedRoles;

    public function __construct(array $allowedRoles = ['admin', 'manager']) {
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * Handles the incoming request and processes it accordingly
     * @param mixed $request The incoming request object
     * @return bool The response object after processing the request
     * @author Rémis Rubis, Mathieu Chauvet
     */
    public function handle() {
        $headers = getallheaders();
        
        // Check if the Authorization header is set
        if (!isset($headers['Authorization'])) {
            // Return an appropriate response or throw an exception
            error_log("Authorization header not set");
            return $this->unauthorizedResponse();
        }

        $authHeader = $headers['Authorization'];

        // Check if the Authorization header contains a bearer token
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            error_log("Bearer token not found in Authorization header");
            return $this->unauthorizedResponse();
        }

        $jwt = $matches[1];

        try {
            $payload = JWT::decryptToken($jwt);
            
            if (!isset($payload['role'])) {
                error_log("No role in token");
                return $this->unauthorizedResponse();
            }

            if (!in_array($payload['role'], $this->allowedRoles)) {
                error_log("Unauthorized role: " . $payload['role']);
                return $this->forbiddenResponse();
            }

            return true;
        } catch (\Exception $e) {
            error_log("JWT verification failed: " . $e->getMessage());
            return $this->unauthorizedResponse();
        }
    }

    /**
     * Helper method to return an unauthorized response
     * @return bool
     * @author Rémis Rubis
     */
    private function unauthorizedResponse() {
        // Here, you could return a response with a 401 status code and an error message
        header('Content-Type: application/json');
        echo json_encode(['error' => "Unauthorized: Invalid or expired token"]);
        http_response_code(401);
        return false;
    }

    /**
     * Helper method to return a forbidden response
     * @return bool
     * @author Mathieu Chauvet
     */
    private function forbiddenResponse() {
        header('Content-Type: application/json');
        echo json_encode(['error' => "Forbidden: Insufficient privileges"]);
        http_response_code(403);
        return false;
    }
}