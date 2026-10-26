<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
start_secure_session();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token = null): void
{
    $submittedToken = $token ?? (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '');
    $knownToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($knownToken) || $knownToken === '' || !hash_equals($knownToken, $submittedToken)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

function set_flash(string $type, string $message): void
{
    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    $_SESSION['flash'] = [
        'type' => in_array($type, $allowedTypes, true) ? $type : 'info',
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

function format_vnd(int|float|string $amount): string
{
    return number_format((float) $amount, 0, ',', '.') . ' ₫';
}
