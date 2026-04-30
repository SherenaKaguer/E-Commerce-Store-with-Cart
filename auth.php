<?php
declare(strict_types=1);

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (function_exists('ini_set')) {
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Must be called before session_start().
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function app_no_cache(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function app_is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function app_current_role(): string
{
    return (string)($_SESSION['role'] ?? '');
}

function app_is_admin(): bool
{
    return app_current_role() === 'admin';
}

function app_dashboard_url(): string
{
    return app_is_admin() ? 'admin_dashboard.php' : 'customer_dashboard.php';
}

function app_redirect(string $path): void
{
    header('Location: ' . $path);
    exit();
}

function app_redirect_to_dashboard(): void
{
    app_redirect(app_dashboard_url());
}

function app_redirect_if_logged_in(): void
{
    if (app_is_logged_in()) {
        app_redirect_to_dashboard();
    }
}

function app_require_login(string $redirect_to = 'login.php'): void
{
    if (!app_is_logged_in()) {
        app_redirect($redirect_to);
    }
}

function app_require_admin(string $redirect_to = 'login.php'): void
{
    if (!app_is_logged_in() || !app_is_admin()) {
        app_redirect($redirect_to);
    }
}

