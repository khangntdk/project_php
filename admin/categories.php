<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';
$pageTitle = 'Quản lý danh mục';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_only();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';

    try {
        if ($action === 'delete' && $id !== false) {
            $statement = db()->prepare('DELETE FROM categories WHERE id = :id');
            $statement->execute([':id' => $id]);
            set_flash($statement->rowCount() > 0 ? 'success' : 'warning', $statement->rowCount() > 0
                ? 'Đã xóa danh mục.'
                : 'Không tìm thấy danh mục cần xóa.');
        } elseif (in_array($action, ['create', 'update'], true)
            && $name !== '' && strlen($name) <= 120 && strlen($description) <= 5000
            && ($action !== 'update' || $id !== false)) {
            $slug = admin_unique_slug(db(), 'categories', $name, $action === 'update' ? $id : null);
            if ($action === 'create') {
                $statement = db()->prepare('INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)');
                $statement->execute([':name' => $name, ':slug' => $slug, ':description' => $description !== '' ? $description : null]);
                set_flash('success', 'Đã thêm danh mục.');
            } else {
                $statement = db()->prepare('UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id');
                $statement->execute([':name' => $name, ':slug' => $slug, ':description' => $description !== '' ? $description : null, ':id' => $id]);
                set_flash('success', 'Đã cập nhật danh mục.');
            }
        } else {
            set_flash('danger', 'Dữ liệu danh mục không hợp lệ.');
        }
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            set_flash('warning', 'Không thể xóa danh mục đang được sản phẩm sử dụng, hoặc tên danh mục bị trùng.');
        } else {
            error_log('TechZone category mutation failed: ' . $exception->getMessage());
            set_flash('danger', 'Không thể lưu thay đổi danh mục.');
        }
    } catch (RuntimeException $exception) {
        error_log('TechZone category database error: ' . $exception->getMessage());
        set_flash('danger', 'Không thể kết nối cơ sở dữ liệu.');
    }
    redirect(BASE_URL . '/admin/categories.php');
}

try {
    $categories = db()->query(
        'SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) AS product_count
         FROM categories c LEFT JOIN products p ON p.category_id = c.id
         GROUP BY c.id, c.name, c.slug, c.description ORDER BY c.name'
    )->fetchAll();
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone categories query failed: ' . $exception->getMessage());
    $categories = [];
    $error = 'Không thể tải danh mục. Vui lòng kiểm tra cơ sở dữ liệu.';
}
require __DIR__ . '/admin-header.php';
?>
<div class="admin-page-heading"><div><h1>Danh mục</h1><p>Quản lý các nhóm sản phẩm của cửa hàng.</p></div></div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="admin-content-grid">
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Thêm danh mục</h2></div>
        <form method="post" class="admin-entity-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div><label class="form-label" for="category-name">Tên danh mục</label><input class="form-control" id="category-name" name="name" maxlength="120" required></div>
            <div><label class="form-label" for="category-description">Mô tả</label><textarea class="form-control" id="category-description" name="description" rows="4" maxlength="5000"></textarea></div>
            <button class="btn btn-techzone" type="submit">Thêm danh mục</button>
        </form>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Danh sách danh mục</h2><span><?= count($categories) ?> danh mục</span></div>
        <div class="admin-entity-list">
            <?php foreach ($categories as $category): ?>
                <article class="admin-entity-item">
                    <form method="post" class="admin-entity-edit">
                        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                        <input class="form-control" name="name" maxlength="120" value="<?= e($category['name']) ?>" aria-label="Tên danh mục" required>
                        <textarea class="form-control" name="description" maxlength="5000" rows="2" aria-label="Mô tả"><?= e($category['description'] ?? '') ?></textarea>
                        <small><?= (int) $category['product_count'] ?> sản phẩm · <?= e($category['slug']) ?></small>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Lưu</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Xóa danh mục này?')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit" <?= (int) $category['product_count'] > 0 ? 'title="Danh mục có sản phẩm đang sử dụng"' : '' ?>>Xóa</button>
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if ($categories === []): ?><div class="empty-state">Chưa có danh mục.</div><?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/admin-footer.php'; ?>
