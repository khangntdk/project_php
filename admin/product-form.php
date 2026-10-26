<?php
declare(strict_types=1);

$pageTitle = isset($product) ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$brands = db()->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_only();
    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $sku = isset($_POST['sku']) && is_string($_POST['sku']) ? strtoupper(trim($_POST['sku'])) : '';
    $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
    $specificationsInput = isset($_POST['specifications']) && is_string($_POST['specifications']) ? trim($_POST['specifications']) : '';
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $brandId = filter_var($_POST['brand_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $priceInput = isset($_POST['price']) && is_string($_POST['price']) ? trim($_POST['price']) : '';
    $salePriceInput = isset($_POST['sale_price']) && is_string($_POST['sale_price']) ? trim($_POST['sale_price']) : '';
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    $featured = isset($_POST['is_featured']) && $_POST['is_featured'] === '1' ? 1 : 0;
    $price = filter_var($priceInput, FILTER_VALIDATE_FLOAT);
    $salePrice = $salePriceInput === '' ? null : filter_var($salePriceInput, FILTER_VALIDATE_FLOAT);
    $specifications = null;
    $imagePath = null;

    if ($name === '' || strlen($name) > 220) {
        $formError = 'Tên sản phẩm không được để trống và tối đa 220 ký tự.';
    } elseif ($sku === '' || strlen($sku) > 80 || !preg_match('/^[A-Z0-9._-]+$/', $sku)) {
        $formError = 'Mã SKU chỉ được chứa chữ in hoa, số, dấu chấm, gạch dưới và gạch ngang.';
    } elseif ($description === '') {
        $formError = 'Vui lòng nhập mô tả sản phẩm.';
    } elseif ($categoryId === false || $brandId === false) {
        $formError = 'Vui lòng chọn danh mục và thương hiệu.';
    } elseif ($price === false || $price < 0 || $price > 9999999999.99) {
        $formError = 'Giá sản phẩm phải là số không âm hợp lệ.';
    } elseif ($salePrice === false || ($salePrice !== null && ($salePrice < 0 || $salePrice > $price))) {
        $formError = 'Giá khuyến mãi phải không âm và không vượt quá giá gốc.';
    } elseif ($stock === false) {
        $formError = 'Tồn kho phải là số nguyên không âm.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $formError = 'Trạng thái sản phẩm không hợp lệ.';
    } elseif ($specificationsInput !== '' && !is_array(json_decode($specificationsInput, true))) {
        $formError = 'Thông số kỹ thuật cần là JSON hợp lệ, ví dụ {"RAM":"16 GB"}.';
    } else {
        $specifications = $specificationsInput === '' ? null : json_encode(
            json_decode($specificationsInput, true),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        try {
            $categoryCheck = db()->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
            $categoryCheck->execute([':id' => $categoryId]);
            $brandCheck = db()->prepare('SELECT COUNT(*) FROM brands WHERE id = :id');
            $brandCheck->execute([':id' => $brandId]);
            if ((int) $categoryCheck->fetchColumn() !== 1 || (int) $brandCheck->fetchColumn() !== 1) {
                throw new RuntimeException('Danh mục hoặc thương hiệu đã chọn không còn tồn tại.');
            }
            if (isset($_FILES['image'])) {
                $imagePath = admin_upload_product_image($_FILES['image']);
            }

            $productId = isset($product['id']) ? (int) $product['id'] : null;
            $slug = admin_unique_slug(db(), 'products', $name, $productId);
            $existingImage = isset($product['image']) && is_string($product['image']) ? $product['image'] : null;
            if ($productId === null) {
                $insert = db()->prepare(
                    'INSERT INTO products
                        (category_id, brand_id, name, slug, sku, description, specifications, price, sale_price,
                         stock, image, status, is_featured)
                     VALUES
                        (:category_id, :brand_id, :name, :slug, :sku, :description, :specifications, :price,
                         :sale_price, :stock, :image, :status, :is_featured)'
                );
                $insert->execute([
                    ':category_id' => $categoryId,
                    ':brand_id' => $brandId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':sku' => $sku,
                    ':description' => $description,
                    ':specifications' => $specifications,
                    ':price' => $price,
                    ':sale_price' => $salePrice,
                    ':stock' => $stock,
                    ':image' => $imagePath,
                    ':status' => $status,
                    ':is_featured' => $featured,
                ]);
                set_flash('success', 'Đã thêm sản phẩm mới.');
            } else {
                $update = db()->prepare(
                    'UPDATE products SET category_id = :category_id, brand_id = :brand_id, name = :name,
                        slug = :slug, sku = :sku, description = :description, specifications = :specifications,
                        price = :price, sale_price = :sale_price, stock = :stock, image = :image,
                        status = :status, is_featured = :is_featured
                     WHERE id = :id'
                );
                $update->execute([
                    ':category_id' => $categoryId,
                    ':brand_id' => $brandId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':sku' => $sku,
                    ':description' => $description,
                    ':specifications' => $specifications,
                    ':price' => $price,
                    ':sale_price' => $salePrice,
                    ':stock' => $stock,
                    ':image' => $imagePath ?? $existingImage,
                    ':status' => $status,
                    ':is_featured' => $featured,
                    ':id' => $productId,
                ]);
                if ($imagePath !== null && $imagePath !== $existingImage) {
                    admin_remove_product_image($existingImage);
                }
                set_flash('success', 'Đã cập nhật sản phẩm.');
            }
            redirect(BASE_URL . '/admin/products.php');
        } catch (RuntimeException $exception) {
            if ($imagePath !== null) {
                admin_remove_product_image($imagePath);
            }
            $formError = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($imagePath !== null) {
                admin_remove_product_image($imagePath);
            }
            error_log('TechZone product save failed: ' . $exception->getMessage());
            $formError = $exception->getCode() === '23000'
                ? 'Mã SKU hoặc đường dẫn sản phẩm đã tồn tại. Vui lòng kiểm tra lại.'
                : 'Không thể lưu sản phẩm. Vui lòng thử lại.';
        }
    }
}

$formValues = [
    'name' => $_POST['name'] ?? $product['name'] ?? '',
    'sku' => $_POST['sku'] ?? $product['sku'] ?? '',
    'description' => $_POST['description'] ?? $product['description'] ?? '',
    'specifications' => $_POST['specifications'] ?? $product['specifications'] ?? '',
    'category_id' => $_POST['category_id'] ?? $product['category_id'] ?? '',
    'brand_id' => $_POST['brand_id'] ?? $product['brand_id'] ?? '',
    'price' => $_POST['price'] ?? $product['price'] ?? '',
    'sale_price' => $_POST['sale_price'] ?? $product['sale_price'] ?? '',
    'stock' => $_POST['stock'] ?? $product['stock'] ?? 0,
    'status' => $_POST['status'] ?? $product['status'] ?? 'active',
    'is_featured' => isset($_POST['is_featured']) ? (int) $_POST['is_featured'] : (int) ($product['is_featured'] ?? 0),
];
?>
<?php require __DIR__ . '/admin-header.php'; ?>
<div class="admin-page-heading"><div><h1><?= isset($product) ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></h1><p>Nhập thông tin và kiểm tra kỹ giá, tồn kho trước khi lưu.</p></div><a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/admin/products.php">Quay lại</a></div>
<section class="admin-panel admin-form-panel">
    <?php if ($formError !== ''): ?><div class="alert alert-danger" role="alert"><?= e($formError) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-product-form">
        <?= csrf_field() ?>
        <div><label class="form-label" for="product-name">Tên sản phẩm</label><input class="form-control" id="product-name" name="name" maxlength="220" value="<?= e($formValues['name']) ?>" required></div>
        <div><label class="form-label" for="product-sku">Mã SKU</label><input class="form-control" id="product-sku" name="sku" maxlength="80" pattern="[A-Za-z0-9._-]+" value="<?= e($formValues['sku']) ?>" required></div>
        <div><label class="form-label" for="product-category">Danh mục</label><select class="form-select" id="product-category" name="category_id" required><option value="">Chọn danh mục</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $formValues['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label" for="product-brand">Thương hiệu</label><select class="form-select" id="product-brand" name="brand_id" required><option value="">Chọn thương hiệu</option><?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['id'] ?>" <?= (string) $formValues['brand_id'] === (string) $brand['id'] ? 'selected' : '' ?>><?= e($brand['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label" for="product-price">Giá gốc (₫)</label><input class="form-control" id="product-price" type="number" name="price" min="0" step="0.01" value="<?= e($formValues['price']) ?>" required></div>
        <div><label class="form-label" for="product-sale-price">Giá khuyến mãi (₫)</label><input class="form-control" id="product-sale-price" type="number" name="sale_price" min="0" step="0.01" value="<?= e($formValues['sale_price']) ?>"><small class="text-secondary">Để trống nếu không giảm giá.</small></div>
        <div><label class="form-label" for="product-stock">Tồn kho</label><input class="form-control" id="product-stock" type="number" name="stock" min="0" step="1" value="<?= e($formValues['stock']) ?>" required></div>
        <div><label class="form-label" for="product-status">Trạng thái</label><select class="form-select" id="product-status" name="status"><option value="active" <?= $formValues['status'] === 'active' ? 'selected' : '' ?>>Đang bán</option><option value="inactive" <?= $formValues['status'] === 'inactive' ? 'selected' : '' ?>>Ngừng bán</option></select></div>
        <div class="admin-field-span"><label class="form-label" for="product-description">Mô tả</label><textarea class="form-control" id="product-description" name="description" rows="5" required><?= e($formValues['description']) ?></textarea></div>
        <div class="admin-field-span"><label class="form-label" for="product-specifications">Thông số kỹ thuật (JSON)</label><textarea class="form-control" id="product-specifications" name="specifications" rows="4" placeholder='{"RAM":"16 GB","Ổ cứng":"512 GB SSD"}'><?= e($formValues['specifications']) ?></textarea></div>
        <div class="admin-field-span"><label class="form-label" for="product-image">Ảnh sản phẩm</label><input class="form-control" id="product-image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="text-secondary">JPEG, PNG hoặc WebP; tối đa 5 MB. Tệp được đổi tên ngẫu nhiên.</small><?php if (!empty($product['image'])): ?><div class="mt-2"><img class="admin-current-image" src="<?= e(BASE_URL . '/' . ltrim($product['image'], '/')) ?>" alt="Ảnh hiện tại"></div><?php endif; ?></div>
        <div class="form-check admin-featured"><input class="form-check-input" type="checkbox" name="is_featured" id="product-featured" value="1" <?= (int) $formValues['is_featured'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="product-featured">Đánh dấu sản phẩm nổi bật</label></div>
        <div class="admin-form-actions"><button class="btn btn-techzone" type="submit">Lưu sản phẩm</button><a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/admin/products.php">Hủy</a></div>
    </form>
</section>
<?php require __DIR__ . '/admin-footer.php'; ?>
