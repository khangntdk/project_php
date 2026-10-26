<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

function admin_post_only(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Phương thức yêu cầu không được hỗ trợ.');
    }

    verify_csrf_token();
}

function admin_slug(string $value): string
{
    $value = trim($value);
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
    return trim($slug, '-') ?: 'item';
}

function admin_unique_slug(PDO $pdo, string $table, string $value, ?int $exceptId = null): string
{
    $allowedTables = ['products', 'categories', 'brands'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Bảng tạo slug không hợp lệ.');
    }

    $base = admin_slug($value);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $parameters = [':slug' => $candidate];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters[':except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        if ($statement->fetchColumn() === false) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix++;
    }
}

function admin_upload_product_image(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Không thể tải ảnh lên. Vui lòng chọn lại tệp.');
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Ảnh sản phẩm phải có dung lượng tối đa 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new RuntimeException('Chỉ hỗ trợ ảnh JPEG, PNG hoặc WebP.');
    }
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Tệp tải lên không phải ảnh hợp lệ.');
    }

    $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Không thể chuẩn bị thư mục lưu ảnh.');
    }
    $fileName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Không thể lưu ảnh sản phẩm.');
    }

    return 'uploads/products/' . $fileName;
}

function admin_remove_product_image(?string $relativePath): void
{
    if (!is_string($relativePath) || !preg_match('/^uploads\/products\/[a-f0-9]{32}\.(jpg|png|webp)$/', $relativePath)) {
        return;
    }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($path) && !unlink($path)) {
        error_log('TechZone could not remove product image: ' . $path);
    }
}

function admin_render_error(string $message): void
{
    set_flash('danger', $message);
}
