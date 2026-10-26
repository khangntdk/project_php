<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$product = null;
$productImages = [];
$relatedProducts = [];
$databaseError = false;

if ($slug === '' || strlen($slug) > 240) {
    http_response_code(404);
} else {
    try {
        $pdo = db();
        $productStatement = $pdo->prepare(
            'SELECT p.id, p.category_id, p.name, p.slug, p.sku, p.description, p.specifications,
                    p.price, p.sale_price, p.stock, p.image, p.created_at,
                    c.name AS category_name, c.slug AS category_slug, b.name AS brand_name
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN brands b ON b.id = p.brand_id
             WHERE p.slug = :slug AND p.status = :status
             LIMIT 1'
        );
        $productStatement->execute([':slug' => $slug, ':status' => 'active']);
        $product = $productStatement->fetch() ?: null;

        if ($product !== null) {
            $imageStatement = $pdo->prepare(
                'SELECT image_path FROM product_images WHERE product_id = :product_id ORDER BY sort_order, id'
            );
            $imageStatement->execute([':product_id' => (int) $product['id']]);
            $productImages = $imageStatement->fetchAll(PDO::FETCH_COLUMN);
            $relatedStatement = $pdo->prepare(
                'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.image, p.stock, b.name AS brand_name
                 FROM products p
                 INNER JOIN brands b ON b.id = p.brand_id
                 WHERE p.category_id = :category_id AND p.id <> :product_id AND p.status = :status
                 ORDER BY p.is_featured DESC, p.created_at DESC
                 LIMIT 4'
            );
            $relatedStatement->execute([
                ':category_id' => (int) $product['category_id'],
                ':product_id' => (int) $product['id'],
                ':status' => 'active',
            ]);
            $relatedProducts = $relatedStatement->fetchAll();
        }
    } catch (PDOException | RuntimeException $exception) {
        error_log('TechZone product detail query failed: ' . $exception->getMessage());
        $databaseError = true;
    }
}

$pageTitle = $product !== null ? (string) $product['name'] : 'Chi tiết sản phẩm';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">Không thể tải thông tin sản phẩm. Vui lòng kiểm tra MySQL và cấu hình cơ sở dữ liệu rồi thử lại.</div>
        <?php elseif ($product === null): ?>
            <div class="empty-state">
                <h1 class="h4">Không tìm thấy sản phẩm</h1>
                <p>Sản phẩm có thể không tồn tại, đã ngừng kinh doanh hoặc đường dẫn không hợp lệ.</p>
                <a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/products.php">Quay lại danh sách sản phẩm</a>
            </div>
        <?php else: ?>
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/products.php?category=<?= rawurlencode($product['category_slug']) ?>"><?= e($product['category_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
                </ol>
            </nav>
            <?php
            $detailPrice = (float) $product['price'];
            $detailSalePrice = $product['sale_price'] !== null ? (float) $product['sale_price'] : null;
            $detailCurrentPrice = $detailSalePrice !== null && $detailSalePrice > 0 ? $detailSalePrice : $detailPrice;
            $gallery = [];
            foreach ($productImages as $imagePath) {
                if (is_string($imagePath) && trim($imagePath) !== '') {
                    $gallery[] = trim($imagePath);
                }
            }
            if (is_string($product['image']) && trim($product['image']) !== '') {
                array_unshift($gallery, trim($product['image']));
            }
            $gallery = array_values(array_unique($gallery));
            $primaryImage = $gallery[0] ?? '';
            $primaryImageUrl = $primaryImage !== ''
                && !str_contains($primaryImage, '..')
                && !str_contains($primaryImage, '://')
                ? BASE_URL . '/' . ltrim($primaryImage, '/')
                : '';
            $specifications = [];
            if (is_string($product['specifications']) && $product['specifications'] !== '') {
                $decodedSpecifications = json_decode($product['specifications'], true);
                if (is_array($decodedSpecifications)) {
                    $specifications = $decodedSpecifications;
                }
            }
            ?>
            <div class="product-detail-grid">
                <div class="product-gallery">
                    <div class="product-detail-image">
                        <?php if ($primaryImageUrl !== ''): ?>
                            <img src="<?= e($primaryImageUrl) ?>" alt="<?= e($product['name']) ?>">
                        <?php else: ?>
                            <span class="product-image-placeholder" aria-hidden="true">TECHZONE</span>
                        <?php endif; ?>
                    </div>
                    <?php if (count($gallery) > 1): ?>
                        <div class="product-thumbnails">
                            <?php foreach ($gallery as $imagePath): ?>
                                <?php
                                $thumbnailUrl = !str_contains($imagePath, '..') && !str_contains($imagePath, '://')
                                    ? BASE_URL . '/' . ltrim($imagePath, '/')
                                    : '';
                                ?>
                                <?php if ($thumbnailUrl !== ''): ?>
                                    <img src="<?= e($thumbnailUrl) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="product-detail-info">
                    <span class="product-detail-category"><?= e($product['category_name']) ?> · <?= e($product['brand_name']) ?></span>
                    <h1><?= e($product['name']) ?></h1>
                    <p class="product-sku">Mã sản phẩm: <strong><?= e($product['sku']) ?></strong></p>
                    <div class="detail-price">
                        <span><?= e(format_vnd($detailCurrentPrice)) ?></span>
                        <?php if ($detailCurrentPrice < $detailPrice): ?>
                            <del><?= e(format_vnd($detailPrice)) ?></del>
                            <span class="discount-badge">-<?= (int) round((1 - $detailCurrentPrice / $detailPrice) * 100) ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="stock-status <?= (int) $product['stock'] > 0 ? 'in-stock' : 'out-stock' ?>">
                        <?= (int) $product['stock'] > 0 ? 'Còn hàng' : 'Tạm hết hàng' ?>
                        <?php if ((int) $product['stock'] > 0): ?><span> · <?= (int) $product['stock'] ?> sản phẩm</span><?php endif; ?>
                    </div>
                    <p class="product-detail-description"><?= nl2br(e($product['description'])) ?></p>
                    <div class="detail-actions">
                        <?php if ((int) $product['stock'] > 0): ?>
                            <form action="<?= e(BASE_URL) ?>/cart.php" method="post" class="detail-cart-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <label for="product-quantity" class="form-label">Số lượng</label>
                                <input id="product-quantity" class="form-control detail-quantity" type="number" name="quantity" min="1" max="<?= (int) $product['stock'] ?>" value="1" required>
                                <button class="btn btn-techzone btn-lg" type="submit" name="action" value="add">Thêm vào giỏ hàng</button>
                                <button class="btn btn-outline-primary btn-lg" type="submit" name="action" value="buy_now">Mua ngay</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg" type="button" disabled>Hết hàng</button>
                        <?php endif; ?>
                    </div>
                    <div class="product-promises">
                        <span>✓ Sản phẩm có nguồn gốc rõ ràng</span>
                        <span>✓ Hỗ trợ bảo hành theo chính sách</span>
                        <span>✓ Thanh toán COD hoặc chuyển khoản thủ công</span>
                    </div>
                </div>
            </div>

            <div class="product-information">
                <section class="product-description-panel">
                    <h2>Mô tả sản phẩm</h2>
                    <p><?= nl2br(e($product['description'])) ?></p>
                </section>
                <?php if ($specifications !== []): ?>
                    <section class="specifications-panel">
                        <h2>Thông số kỹ thuật</h2>
                        <dl>
                            <?php foreach ($specifications as $key => $value): ?>
                                <div><dt><?= e(is_string($key) ? $key : 'Thông số') ?></dt><dd><?= e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if (!$databaseError && $product !== null): ?>
    <section class="section section-soft">
        <div class="container">
            <div class="section-heading"><div><h2>Sản phẩm liên quan</h2><p>Các lựa chọn khác trong cùng danh mục</p></div></div>
            <?php if ($relatedProducts === []): ?>
                <div class="empty-state">Chưa có sản phẩm liên quan trong danh mục này.</div>
            <?php else: ?>
                <div class="row g-3 g-lg-4">
                    <?php foreach ($relatedProducts as $product): ?>
                        <div class="col-6 col-lg-3">
                            <?php require __DIR__ . '/includes/product-card.php'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
