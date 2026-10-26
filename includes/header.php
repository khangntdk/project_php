<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Thiết bị công nghệ chính hãng';
$user = current_user();
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cartCount = array_sum(array_map(
    static fn (mixed $quantity): int => is_numeric($quantity) ? max(0, (int) $quantity) : 0,
    $cart
));
$flash = get_flash();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102a56">
    <title><?= e($pageTitle) ?> | TechZone</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="header-main">
            <a class="brand" href="<?= e(BASE_URL) ?>/index.php" aria-label="TechZone - Trang chủ">
                <span class="brand-mark">T</span><span>Tech<span class="brand-accent">Zone</span></span>
            </a>
            <form class="header-search" action="<?= e(BASE_URL) ?>/products.php" method="get" role="search">
                <label class="visually-hidden" for="site-search">Tìm kiếm sản phẩm</label>
                <input id="site-search" type="search" name="q" placeholder="Bạn cần tìm sản phẩm nào?" value="<?= e($searchQuery) ?>">
                <button type="submit" aria-label="Tìm kiếm">Tìm kiếm</button>
            </form>
            <nav class="header-actions" aria-label="Tài khoản và giỏ hàng">
                <?php if ($user !== null): ?>
                    <a class="header-action" href="<?= e(BASE_URL) ?>/profile.php">
                        <span class="action-icon" aria-hidden="true">♙</span>
                        <span><?= e($user['full_name'] ?? 'Tài khoản') ?></span>
                    </a>
                    <?php if (($user['role'] ?? null) === 'admin'): ?>
                        <a class="header-action" href="<?= e(BASE_URL) ?>/admin/index.php">
                            <span class="action-icon" aria-hidden="true">⚙</span><span>Quản trị</span>
                        </a>
                    <?php endif; ?>
                    <form action="<?= e(BASE_URL) ?>/logout.php" method="post" class="header-logout">
                        <?= csrf_field() ?>
                        <button class="header-action header-action-button" type="submit">Đăng xuất</button>
                    </form>
                <?php else: ?>
                    <a class="header-action" href="<?= e(BASE_URL) ?>/login.php">
                        <span class="action-icon" aria-hidden="true">♙</span><span>Tài khoản</span>
                    </a>
                <?php endif; ?>
                <a class="header-action cart-link" href="<?= e(BASE_URL) ?>/cart.php">
                    <span class="action-icon" aria-hidden="true">🛒</span>
                    <span>Giỏ hàng <b class="cart-count"><?= (int) $cartCount ?></b></span>
                </a>
            </nav>
        </div>
        <nav class="category-nav" aria-label="Danh mục chính">
            <a href="<?= e(BASE_URL) ?>/products.php">Tất cả sản phẩm</a>
            <a href="<?= e(BASE_URL) ?>/products.php?category=laptop">Laptop</a>
            <a href="<?= e(BASE_URL) ?>/products.php?category=dien-thoai">Điện thoại</a>
            <a href="<?= e(BASE_URL) ?>/products.php?category=may-tinh-bang">Máy tính bảng</a>
            <a href="<?= e(BASE_URL) ?>/products.php?category=man-hinh">Màn hình</a>
            <a href="<?= e(BASE_URL) ?>/products.php?category=linh-kien-pc">Linh kiện PC</a>
            <a href="<?= e(BASE_URL) ?>/products.php?category=phu-kien">Phụ kiện</a>
        </nav>
    </div>
</header>
<main class="page-content">
    <?php if ($flash !== null): ?>
        <div class="container pt-3">
            <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        </div>
    <?php endif; ?>
