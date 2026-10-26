<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once dirname(__DIR__) . '/config/database.php';

function require_login(): void
{
    if (current_user() === null) {
        set_flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect(BASE_URL . '/login.php');
    }
}

function require_admin(): void
{
    require_login();

    $userId = filter_var(current_user()['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($userId === false) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }

    try {
        $statement = db()->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $userId]);
        $role = $statement->fetchColumn();
    } catch (PDOException | RuntimeException $exception) {
        error_log('TechZone admin authorization check failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('Không thể xác thực quyền quản trị lúc này. Vui lòng thử lại sau.');
    }

    if (!in_array($role, ['admin', 'customer'], true)) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }

    $_SESSION['user']['role'] = $role;
    if ($role !== 'admin') {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }
}
