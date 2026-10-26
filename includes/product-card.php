<?php
declare(strict_types=1);

if (!isset($product) || !is_array($product)) {
    throw new RuntimeException('Thiếu dữ liệu sản phẩm để hiển thị.');
}

$productPrice = (float) $product['price'];
$productSalePrice = $product['sale_price'] !== null ? (float) $product['sale_price'] : null;
$productCurrentPrice = $productSalePrice !== null && $productSalePrice > 0
    ? $productSalePrice
    : $productPrice;
$productDiscount = $productPrice > 0 && $productCurrentPrice < $productPrice
    ? (int) round((1 - $productCurrentPrice / $productPrice) * 100)
    : 0;
$productImage = isset($product['image']) && is_string($product['image'])
    ? trim($product['image'])
    : '';
$productImageUrl = $productImage !== ''
    && !str_contains($productImage, '..')
    && !str_contains($productImage, '://')
    ? BASE_URL . '/' . ltrim($productImage, '/')
    : '';
?>
<article class="product-card">
    <a class="product-card-image" href="<?= e(BASE_URL) ?>/product-detail.php?slug=<?= rawurlencode((string) $product['slug']) ?>">
        <?php if ($productImageUrl !== ''): ?>
            <img src="<?= e($productImageUrl) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
        <?php else: ?>
            <span class="product-image-placeholder" aria-hidden="true">TECHZONE</span>
        <?php endif; ?>
        <?php if ($productDiscount > 0): ?>
            <span class="discount-badge">-<?= $productDiscount ?>%</span>
        <?php endif; ?>
    </a>
    <div class="product-card-body">
        <?php if (!empty($product['brand_name'])): ?>
            <div class="product-brand"><?= e($product['brand_name']) ?></div>
        <?php endif; ?>
        <h3 class="product-card-title">
            <a href="<?= e(BASE_URL) ?>/product-detail.php?slug=<?= rawurlencode((string) $product['slug']) ?>"><?= e($product['name']) ?></a>
        </h3>
        <div class="product-price-line">
            <span class="product-price"><?= e(format_vnd($productCurrentPrice)) ?></span>
            <?php if ($productDiscount > 0): ?>
                <span class="product-old-price"><?= e(format_vnd($productPrice)) ?></span>
            <?php endif; ?>
        </div>
        <div class="product-card-actions">
            <a class="btn btn-outline-primary w-100 mt-3" href="<?= e(BASE_URL) ?>/product-detail.php?slug=<?= rawurlencode((string) $product['slug']) ?>">Xem chi tiết</a>
            <form action="<?= e(BASE_URL) ?>/cart.php" method="post" class="mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button class="btn btn-techzone w-100" type="submit" <?= isset($product['stock']) && (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
                    <?= isset($product['stock']) && (int) $product['stock'] < 1 ? 'Hết hàng' : 'Thêm vào giỏ' ?>
                </button>
            </form>
        </div>
    </div>
</article>
