<?php 

namespace App\Middlewares;

use App\Utils\JWT;

class AuthMiddleware {
    public function handle($request) {
        $headers = getallheaders();
        
        // Check if the Authorization header is set
        if (!isset($headers['Authorization'])) {
            // Return an appropriate response or throw an exception
            return $this->unauthorizedResponse();
        }

        $authHeader = $headers['Authorization'];

        // Check if the Authorization header contains a bearer token
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorizedResponse();
        }

        $jwt = $matches[1];

        // Verify the JWT and return the result
        if (!JWT::verify($jwt)) {
            return $this->unauthorizedResponse();
        }

        // Proceed with the request if JWT is valid
        return true;
    }

    // Helper method to return an unauthorized response
    private function unauthorizedResponse() {
        // Here, you could return a response with a 401 status code and an error message
        echo json_encode(['error' => "Unauthorized"]);
        http_response_code(401);
        return false;
    }
}