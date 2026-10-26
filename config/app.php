<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', rtrim((string) (getenv('TECHZONE_BASE_URL') ?: '/techzone'), '/'));

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent($file, $line)) {
        throw new RuntimeException("Không thể khởi tạo phiên sau khi gửi nội dung ({$file}:{$line}).");
    }

    session_name('techzone_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Không thể khởi tạo phiên làm việc.');
    }
}
