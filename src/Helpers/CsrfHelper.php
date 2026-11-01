<?php

class CsrfHelper
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function valid(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token);
    }

    public static function requireValid(): void
    {
        if (!self::valid($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}
