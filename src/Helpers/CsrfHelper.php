<?php

class CsrfHelper
{
    public static function token(): string
    {
        if (class_exists('SessionHelper')) {
            return SessionHelper::csrfToken();
        }
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return (string) $_SESSION['_csrf'];
    }

    public static function valid(?string $token): bool
    {
        return is_string($token) && $token !== '' && (
            hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token)
            || (class_exists('SessionHelper') && SessionHelper::csrfValidate($token))
        );
    }

    public static function requireValid(): void
    {
        if (!self::valid($_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
            && !SessionHelper::csrfValidate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}
