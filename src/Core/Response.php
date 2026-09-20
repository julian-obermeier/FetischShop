<?php
namespace App\Core;

final class Response
{
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), payment=(), usb=(), camera=(self), microphone=(self)');
        header('Cross-Origin-Resource-Policy: same-origin');

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function redirect(string $to, int $status = 302): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }

    public static function json(array $d, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo htmlspecialchars($message ?: 'Fehler ' . $status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        exit;
    }
}
