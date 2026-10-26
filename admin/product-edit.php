<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';
$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = null;
if ($productId !== false) {
    try {
        $statement = db()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $productId]);
        $product = $statement->fetch() ?: null;
    } catch (PDOException | RuntimeException $exception) {
        error_log('TechZone edit product lookup failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Không thể tải sản phẩm do lỗi cơ sở dữ liệu.');
    }
}
if ($product === null) {
    http_response_code(404);
    exit('Không tìm thấy sản phẩm.');
}
$pageTitle = 'Sửa sản phẩm';
try {
    require __DIR__ . '/product-form.php';
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone edit product form failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Không thể tải biểu mẫu sản phẩm do lỗi cơ sở dữ liệu.';
}
