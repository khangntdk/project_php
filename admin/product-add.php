<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';
$product = null;
$pageTitle = 'Thêm sản phẩm';
try {
    require __DIR__ . '/product-form.php';
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone add product form failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Không thể tải biểu mẫu sản phẩm do lỗi cơ sở dữ liệu.';
}
