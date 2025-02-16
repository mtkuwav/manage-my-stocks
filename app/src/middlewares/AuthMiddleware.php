<?php 

namespace App\Middlewares;

use App\Utils\JWT;

class AuthMiddleware {

    /**
     * Handles the incoming request and processes it accordingly
     * @param mixed $request The incoming request object
     * @return bool The response object after processing the request
     * @author Rémis Rubis
     */
    public function handle($request) {
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

        // Verify the JWT and return the result
        if (!JWT::verify($jwt)) {
            error_log("JWT verification failed");
            return $this->unauthorizedResponse();
        }

        // Proceed with the request if JWT is valid
        return true;
    }

    /**
     * Helper method to return an unauthorized response
     * @return bool
     * @author Rémis Rubis
     */
    private function unauthorizedResponse() {
        // Here, you could return a response with a 401 status code and an error message
        echo json_encode(['error' => "Unauthorized"]);
        http_response_code(401);
        return false;
    }
}