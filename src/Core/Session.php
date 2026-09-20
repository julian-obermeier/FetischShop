<?php
namespace App\Core;

final class Session
{
    private const IDLE_TIMEOUT = 28800;
    private const ABSOLUTE_TIMEOUT = 604800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_name('fetischshop_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $started = (int) ($_SESSION['_started_at'] ?? 0);
        $lastSeen = (int) ($_SESSION['_last_seen_at'] ?? 0);

        if (($started > 0 && $now - $started > self::ABSOLUTE_TIMEOUT)
            || ($lastSeen > 0 && $now - $lastSeen > self::IDLE_TIMEOUT)) {
            self::destroy();
            session_start();
            $started = 0;
        }

        if ($started === 0) {
            session_regenerate_id(true);
            $_SESSION['_started_at'] = $now;
        }

        $_SESSION['_last_seen_at'] = $now;
    }

    public static function get(string $k, mixed $d = null): mixed { return $_SESSION[$k] ?? $d; }
    public static function put(string $k, mixed $v): void { $_SESSION[$k] = $v; }
    public static function forget(string $k): void { unset($_SESSION[$k]); }
    public static function flash(string $k, mixed $v): void { $_SESSION['_flash'][$k] = $v; }
    public static function pullFlash(string $k, mixed $d = null): mixed { $v = $_SESSION['_flash'][$k] ?? $d; unset($_SESSION['_flash'][$k]); return $v; }
    public static function regenerate(): void { session_regenerate_id(true); $_SESSION['_last_seen_at'] = time(); }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }
}
