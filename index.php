<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Trang chủ';
$categories = [];
$featuredProducts = [];
$latestProducts = [];
$saleProducts = [];
$databaseError = false;

try {
    $pdo = db();
    $categories = $pdo->query(
        'SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
         FROM categories c
         LEFT JOIN products p ON p.category_id = c.id AND p.status = "active"
         GROUP BY c.id, c.name, c.slug
         ORDER BY c.name'
    )->fetchAll();

    $productSelect = 'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.image, p.stock,
                             b.name AS brand_name
                      FROM products p
                      INNER JOIN brands b ON b.id = p.brand_id
                      WHERE p.status = "active"';
    $featuredStatement = $pdo->query($productSelect . ' AND p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 8');
    $featuredProducts = $featuredStatement->fetchAll();
    $latestProducts = $pdo->query($productSelect . ' ORDER BY p.created_at DESC LIMIT 8')->fetchAll();
    $saleProducts = $pdo->query(
        $productSelect . ' AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price
         ORDER BY (p.price - p.sale_price) / p.price DESC, p.created_at DESC LIMIT 8'
    )->fetchAll();
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone home page query failed: ' . $exception->getMessage());
    $databaseError = true;
}

require __DIR__ . '/includes/header.php';
?>
<section class="home-hero">
    <div class="container hero-content">
        <div class="hero-copy">
            <span class="hero-eyebrow">CÔNG NGHỆ CHÍNH HÃNG · DỊCH VỤ TẬN TÂM</span>
            <h1>Nâng tầm trải nghiệm<br>cùng <span>TechZone</span></h1>
            <p>Khám phá laptop, điện thoại và thiết bị công nghệ phù hợp với bạn. Thông tin sản phẩm rõ ràng, giá bán minh bạch.</p>
            <a class="btn btn-light btn-lg hero-button" href="<?= e(BASE_URL) ?>/products.php">Khám phá sản phẩm <span aria-hidden="true">→</span></a>
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="hero-orbit orbit-one"></div>
            <div class="hero-orbit orbit-two"></div>
            <div class="hero-device device-laptop"><span></span></div>
            <div class="hero-device device-phone"><span></span></div>
            <div class="hero-glow"></div>
        </div>
    </div>
</section>

<?php if ($databaseError): ?>
    <div class="container pt-4">
        <div class="alert alert-danger" role="alert">Không thể tải dữ liệu sản phẩm. Vui lòng kiểm tra MySQL và cấu hình cơ sở dữ liệu rồi thử tải lại.</div>
    </div>
<?php else: ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <div><h2>Danh mục sản phẩm</h2><p>Tìm nhanh nhóm sản phẩm bạn quan tâm</p></div>
                <a class="section-link" href="<?= e(BASE_URL) ?>/products.php">Tất cả sản phẩm <span aria-hidden="true">→</span></a>
            </div>
            <?php if ($categories === []): ?>
                <div class="empty-state">Chưa có danh mục sản phẩm. Hãy thêm dữ liệu danh mục trong cơ sở dữ liệu.</div>
            <?php else: ?>
                <div class="category-grid">
                    <?php foreach ($categories as $category): ?>
                        <a class="category-tile" href="<?= e(BASE_URL) ?>/products.php?category=<?= rawurlencode($category['slug']) ?>">
                            <span class="category-tile-icon" aria-hidden="true">TZ</span>
                            <span class="category-tile-name"><?= e($category['name']) ?></span>
                            <span class="category-tile-count"><?= (int) $category['product_count'] ?> sản phẩm</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php
    $productSections = [
        ['title' => 'Sản phẩm nổi bật', 'description' => 'Những lựa chọn được TechZone tuyển chọn', 'products' => $featuredProducts, 'class' => ''],
        ['title' => 'Ưu đãi dành cho bạn', 'description' => 'Giá khuyến mãi được cập nhật từ cửa hàng', 'products' => $saleProducts, 'class' => 'section-soft'],
        ['title' => 'Sản phẩm mới nhất', 'description' => 'Khám phá những sản phẩm mới có tại TechZone', 'products' => $latestProducts, 'class' => ''],
    ];
    ?>
    <?php foreach ($productSections as $section): ?>
        <section class="section <?= e($section['class']) ?>">
            <div class="container">
                <div class="section-heading">
                    <div><h2><?= e($section['title']) ?></h2><p><?= e($section['description']) ?></p></div>
                    <a class="section-link" href="<?= e(BASE_URL) ?>/products.php">Xem tất cả <span aria-hidden="true">→</span></a>
                </div>
                <?php if ($section['products'] === []): ?>
                    <div class="empty-state">Chưa có sản phẩm trong mục này.</div>
                <?php else: ?>
                    <div class="row g-3 g-lg-4">
                        <?php foreach ($section['products'] as $product): ?>
                            <div class="col-6 col-lg-3">
                                <?php require __DIR__ . '/includes/product-card.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
