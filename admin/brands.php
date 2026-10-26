<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';
$pageTitle = 'Quản lý thương hiệu';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_only();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    try {
        if ($action === 'delete' && $id !== false) {
            $statement = db()->prepare('DELETE FROM brands WHERE id = :id');
            $statement->execute([':id' => $id]);
            set_flash($statement->rowCount() > 0 ? 'success' : 'warning', $statement->rowCount() > 0
                ? 'Đã xóa thương hiệu.'
                : 'Không tìm thấy thương hiệu cần xóa.');
        } elseif (in_array($action, ['create', 'update'], true)
            && $name !== '' && strlen($name) <= 120
            && ($action !== 'update' || $id !== false)) {
            $slug = admin_unique_slug(db(), 'brands', $name, $action === 'update' ? $id : null);
            if ($action === 'create') {
                $statement = db()->prepare('INSERT INTO brands (name, slug) VALUES (:name, :slug)');
                $statement->execute([':name' => $name, ':slug' => $slug]);
                set_flash('success', 'Đã thêm thương hiệu.');
            } else {
                $statement = db()->prepare('UPDATE brands SET name = :name, slug = :slug WHERE id = :id');
                $statement->execute([':name' => $name, ':slug' => $slug, ':id' => $id]);
                set_flash('success', 'Đã cập nhật thương hiệu.');
            }
        } else {
            set_flash('danger', 'Dữ liệu thương hiệu không hợp lệ.');
        }
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            set_flash('warning', 'Không thể xóa thương hiệu đang được sản phẩm sử dụng, hoặc tên thương hiệu bị trùng.');
        } else {
            error_log('TechZone brand mutation failed: ' . $exception->getMessage());
            set_flash('danger', 'Không thể lưu thay đổi thương hiệu.');
        }
    } catch (RuntimeException $exception) {
        error_log('TechZone brand database error: ' . $exception->getMessage());
        set_flash('danger', 'Không thể kết nối cơ sở dữ liệu.');
    }
    redirect(BASE_URL . '/admin/brands.php');
}

try {
    $brands = db()->query(
        'SELECT b.id, b.name, b.slug, COUNT(p.id) AS product_count
         FROM brands b LEFT JOIN products p ON p.brand_id = b.id
         GROUP BY b.id, b.name, b.slug ORDER BY b.name'
    )->fetchAll();
    $databaseError = false;
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone brands query failed: ' . $exception->getMessage());
    $brands = [];
    $databaseError = true;
}
require __DIR__ . '/admin-header.php';
?>
<div class="admin-page-heading"><div><h1>Thương hiệu</h1><p>Quản lý thương hiệu đang có trong danh mục sản phẩm.</p></div></div>
<?php if ($databaseError): ?><div class="alert alert-danger">Không thể tải thương hiệu. Vui lòng kiểm tra cơ sở dữ liệu.</div><?php endif; ?>
<div class="admin-content-grid">
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Thêm thương hiệu</h2></div>
        <form method="post" class="admin-entity-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div><label class="form-label" for="brand-name">Tên thương hiệu</label><input class="form-control" id="brand-name" name="name" maxlength="120" required></div>
            <button class="btn btn-techzone" type="submit">Thêm thương hiệu</button>
        </form>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Danh sách thương hiệu</h2><span><?= count($brands) ?> thương hiệu</span></div>
        <div class="admin-entity-list">
            <?php foreach ($brands as $brand): ?>
                <article class="admin-entity-item">
                    <form method="post" class="admin-brand-edit">
                        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $brand['id'] ?>">
                        <div><input class="form-control" name="name" maxlength="120" value="<?= e($brand['name']) ?>" aria-label="Tên thương hiệu" required><small><?= (int) $brand['product_count'] ?> sản phẩm · <?= e($brand['slug']) ?></small></div>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Lưu</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Xóa thương hiệu này?')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $brand['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= (int) $brand['product_count'] > 0 ? 'Thương hiệu có sản phẩm đang sử dụng' : '' ?>">Xóa</button>
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if ($brands === []): ?><div class="empty-state">Chưa có thương hiệu.</div><?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/admin-footer.php'; ?>
