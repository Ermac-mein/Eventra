<?php

namespace App\Helpers;

class JWTHelper
{
    private static $secret = null;

    /**
     * Get JWT secret from environment variable
     */
    private static function getSecret()
    {
        if (self::$secret === null) {
            self::$secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
            
            if (empty(self::$secret)) {
                throw new \RuntimeException('JWT_SECRET environment variable is not set');
            }
        }
        return self::$secret;
    }

    public static function generateJWT($payload)
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$header.$payload", self::getSecret(), true);
        $signature = self::base64UrlEncode($signature);

        return "$header.$payload.$signature";
    }

    public static function validateJWT($jwt)
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $validSignature = hash_hmac('sha256', "$header.$payload", self::getSecret(), true);
        $validSignature = self::base64UrlEncode($validSignature);

        if (!hash_equals($validSignature, $signature)) {
            return false;
        }

        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);
        if (!$decodedPayload) {
            return false;
        }

        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false;
        }

        return $decodedPayload;
    }

    private static function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}

