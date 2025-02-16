<?php

namespace App\Utils;

class JWT {
  private static $secret = "mon-super-secret";

  /**
   * Generate a JSON Web Token (JWT) from the given payload.
   *
   * This function creates a JWT by encoding the header and payload using base64 URL encoding,
   * concatenating them, and then generating a signature using HMAC with SHA-256.
   *
   * @param array $payload The payload data to include in the JWT.
   * @return string The generated JWT.
   * @author Rémis Rubis
   */
  public static function generate($payload) {
    // Base 64
      // Header
    $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    // Payload
    $payload = self::base64UrlEncode(json_encode($payload));
    
    // Concatenation header . payload
    $concat_signature = "$header.$payload";
    // Signature generation with hash
    $signature = hash_hmac("sha256", $concat_signature, self::$secret, true);
      //  Signature's base64
    $signature = self::base64UrlEncode($signature);

    // Return -> header . payload . signature
    return "$header.$payload.$signature";
  }

  /**
   * Decrypt the informations contained in a JWT token.
   * @param mixed $token The token to decode
   * @return object The decoded payload
   * @author Mathieu Chauvet
   */
  public static function decryptToken($token) {
    $segments = explode('.', $token);
    if (count($segments) !== 3) {
        throw new \Exception("Invalid token structure");
    }
    
    list($header, $payload, $signature) = $segments;

    $expectedSignature = self::base64UrlEncode(
        hash_hmac('sha256', "$header.$payload", self::$secret, true)
    );
    
    if (!hash_equals($expectedSignature, $signature)) {
        throw new \Exception("Invalid signature");
    }

    $decodedPayload = json_decode(
        base64_decode(strtr($payload, '-_', '+/')), 
        true
    );

    if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
        throw new \Exception("Token has expired");
    }
    
    return $decodedPayload;
  }

  /**
   * Verify the integrity of a JWT token
   * 
   * @param string $jwt The JWT token to verify
   * @return bool True if the token is valid, false otherwise
   * @author Rémis Rubis
   */
  public static function verify($jwt) {
    // Ensure the JWT has the correct number of segments
    $segments = explode('.', $jwt);
    if (count($segments) !== 3) {
        return false;  // Invalid JWT structure
    }

    list($header, $payload, $signature) = $segments;
    $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", self::$secret, true));
    
    return hash_equals($expectedSignature, $signature);
}
  
  /**
   * Encodes the given data with base64 URL encoding.
   *
   * This function encodes the input data using base64 encoding and then
   * makes the encoded string URL-safe by replacing '+' with '-', '/' with '_',
   * and removing any trailing '=' characters.
   *
   * @param string $data The data to be encoded.
   * @return string The base64 URL encoded string.
   * @author Rémis Rubis
   */
  private static function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), characters: '=');
  }
}