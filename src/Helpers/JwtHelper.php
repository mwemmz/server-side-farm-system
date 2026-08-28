<?php
// Minimal JWT implementation using PHP's built-in HMAC (no external dependency).
// Tokens are HS256-signed using JWT_SECRET (from env). Used by the /api/v1 layer.

require_once __DIR__ . '/../../config/env.php';

class JwtHelper {

    /** Base64Url encode. */
    private static function b64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Base64Url decode. */
    private static function b64urlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Create a signed JWT.
     * @param int $userId
     * @param string $role
     * @param int $ttlSeconds
     * @return string
     */
    public static function encode($userId, $role, $ttlSeconds = 3600) {
        $secret = getEnvVar('JWT_SECRET', 'ffms_change_me_secret');
        $header  = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = [
            'sub'  => (int) $userId,
            'role' => (string) $role,
            'iat'  => time(),
            'exp'  => time() + $ttlSeconds,
        ];
        $segments = [
            self::b64url(json_encode($header)),
            self::b64url(json_encode($payload)),
        ];
        $signingInput  = implode('.', $segments);
        $signature     = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[]    = self::b64url($signature);
        return implode('.', $segments);
    }

    /**
     * Verify a JWT and return its payload, or null if invalid/expired.
     * @param string $token
     * @return array|null
     */
    public static function verify($token) {
        if (!is_string($token) || $token === '') {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $secret = getEnvVar('JWT_SECRET', 'ffms_change_me_secret');
        $signingInput = $parts[0] . '.' . $parts[1];
        $expected = self::b64url(hash_hmac('sha256', $signingInput, $secret, true));
        if (!hash_equals($expected, $parts[2])) {
            return null;
        }
        $payload = json_decode(self::b64urlDecode($parts[1]), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    /** Extract the Bearer token from the Authorization header. @return string|null */
    public static function tokenFromHeader() {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION'];
        } else {
            return null;
        }
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
