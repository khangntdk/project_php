<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';

$pageTitle = 'Quản lý sản phẩm';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentPage = $requestedPage === false ? 1 : $requestedPage;
$perPage = 15;
$products = [];
$totalPages = 1;
$databaseError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_only();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($action !== 'delete' || $id === false) {
        admin_render_error('Thao tác sản phẩm không hợp lệ.');
    } else {
        try {
            $pdo = db();
            $imageStatement = $pdo->prepare(
                'SELECT image FROM products WHERE id = :product_id UNION ALL
                 SELECT image_path FROM product_images WHERE product_id = :gallery_product_id'
            );
            $imageStatement->execute([':product_id' => $id, ':gallery_product_id' => $id]);
            $imagePaths = $imageStatement->fetchAll(PDO::FETCH_COLUMN);
            $statement = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $statement->execute([':id' => $id]);
            if ($statement->rowCount() === 0) {
                set_flash('warning', 'Không tìm thấy sản phẩm cần xóa.');
            } else {
                foreach ($imagePaths as $imagePath) {
                    admin_remove_product_image(is_string($imagePath) ? $imagePath : null);
                }
                set_flash('success', 'Đã xóa sản phẩm.');
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                try {
                    $deactivate = db()->prepare("UPDATE products SET status = 'inactive' WHERE id = :id");
                    $deactivate->execute([':id' => $id]);
                    set_flash('warning', 'Sản phẩm đã có trong đơn hàng nên không thể xóa. Đã chuyển sản phẩm sang trạng thái ngừng kinh doanh.');
                } catch (PDOException $updateException) {
                    error_log('TechZone product deactivation failed: ' . $updateException->getMessage());
                    set_flash('danger', 'Không thể xóa hoặc ngừng kinh doanh sản phẩm.');
                }
            } else {
                error_log('TechZone product deletion failed: ' . $exception->getMessage());
                set_flash('danger', 'Không thể xóa sản phẩm. Vui lòng thử lại.');
            }
        } catch (RuntimeException $exception) {
            error_log('TechZone product delete database error: ' . $exception->getMessage());
            set_flash('danger', 'Không thể kết nối cơ sở dữ liệu.');
        }
    }
    redirect(BASE_URL . '/admin/products.php');
}

try {
    $pdo = db();
    $where = '';
    $parameters = [];
    if ($search !== '') {
        $where = ' WHERE p.name LIKE :name OR p.sku LIKE :sku';
        $parameters = [':name' => '%' . $search . '%', ':sku' => '%' . $search . '%'];
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM products p' . $where);
    $count->execute($parameters);
    $countProducts = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($countProducts / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $statement = $pdo->prepare(
        'SELECT p.id, p.name, p.sku, p.price, p.sale_price, p.stock, p.status,
                c.name AS category_name, b.name AS brand_name
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         INNER JOIN brands b ON b.id = p.brand_id'
        . $where
        . ' ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $key => $value) {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($currentPage - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $products = $statement->fetchAll();
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone admin products query failed: ' . $exception->getMessage());
    $databaseError = true;
}

require __DIR__ . '/admin-header.php';
?>
<div class="admin-page-heading">
    <div><h1>Sản phẩm</h1><p>Thêm, cập nhật và quản lý tồn kho sản phẩm.</p></div>
    <a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/admin/product-add.php">+ Thêm sản phẩm</a>
</div>
<?php if ($databaseError): ?>
    <div class="alert alert-danger" role="alert">Không thể tải sản phẩm. Hãy kiểm tra database.sql đã được import chưa.</div>
<?php else: ?>
    <section class="admin-panel">
        <form class="admin-search-form" method="get">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Tìm theo tên hoặc mã SKU">
            <button class="btn btn-outline-primary" type="submit">Tìm kiếm</button>
        </form>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead><tr><th>Sản phẩm</th><th>Danh mục / thương hiệu</th><th>Giá</th><th>Tồn kho</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><strong><?= e($product['name']) ?></strong><small><?= e($product['sku']) ?></small></td>
                        <td><?= e($product['category_name']) ?><small><?= e($product['brand_name']) ?></small></td>
                        <td><?= e(format_vnd($product['sale_price'] ?: $product['price'])) ?><?php if ($product['sale_price'] !== null): ?><small><del><?= e(format_vnd($product['price'])) ?></del></small><?php endif; ?></td>
                        <td><?= (int) $product['stock'] ?></td>
                        <td><span class="badge text-bg-<?= $product['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $product['status'] === 'active' ? 'Đang bán' : 'Ngừng bán' ?></span></td>
                        <td>
                            <div class="admin-row-actions">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/admin/product-edit.php?id=<?= (int) $product['id'] ?>">Sửa</a>
                                <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa sản phẩm này?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($products === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Không tìm thấy sản phẩm.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Phân trang sản phẩm"><ul class="pagination justify-content-center mb-0">
                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(['q' => $search, 'page' => $page])) ?>"><?= $page ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/admin-footer.php'; ?>
