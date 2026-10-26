<?php
declare(strict_types=1);

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Quản trị';
$adminFlash = get_flash();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | TechZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <div class="container-fluid admin-topbar-inner">
        <a class="admin-brand" href="<?= e(BASE_URL) ?>/admin/index.php"><span class="brand-mark">T</span><span>TechZone <small>ADMIN</small></span></a>
        <div class="admin-top-actions">
            <span><?= e(current_user()['full_name'] ?? 'Quản trị viên') ?></span>
            <a href="<?= e(BASE_URL) ?>/index.php">Xem cửa hàng</a>
            <form action="<?= e(BASE_URL) ?>/logout.php" method="post">
                <?= csrf_field() ?>
                <button type="submit">Đăng xuất</button>
            </form>
        </div>
    </div>
</header>
<div class="container-fluid admin-shell">
    <aside class="admin-sidebar">
        <span class="admin-nav-caption">TỔNG QUAN</span>
        <a href="<?= e(BASE_URL) ?>/admin/index.php">Dashboard</a>
        <span class="admin-nav-caption">QUẢN LÝ CỬA HÀNG</span>
        <a href="<?= e(BASE_URL) ?>/admin/products.php">Sản phẩm</a>
        <a href="<?= e(BASE_URL) ?>/admin/categories.php">Danh mục</a>
        <a href="<?= e(BASE_URL) ?>/admin/brands.php">Thương hiệu</a>
        <a href="<?= e(BASE_URL) ?>/admin/orders.php">Đơn hàng</a>
        <a href="<?= e(BASE_URL) ?>/admin/users.php">Người dùng</a>
    </aside>
    <main class="admin-main">
        <?php if ($adminFlash !== null): ?>
            <div class="alert alert-<?= e($adminFlash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= e($adminFlash['message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        <?php endif; ?>
