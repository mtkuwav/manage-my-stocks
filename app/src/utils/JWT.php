<?php

namespace App\Utils;

class JWT {
  private static $secret = "mon-super-secret";

  public static function generate($payload) {
    // Base 64
      // Header
    $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    // Payload
    $payload = self::base64UrlEncode(json_encode($payload));
    
    // Concaténation header . payload
    $concat_signature = "$header.$payload";
    // Génération de la signature avec hash
    $signature = hash_hmac("sha256", $concat_signature, self::$secret, true);
      //  base64 de la signature
    $signature = self::base64UrlEncode($signature);

    // Return -> header . payload . signature
    return "$header.$payload.$signature";
  }

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
  
  private static function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), characters: '=');
  }
}