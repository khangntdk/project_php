<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';
$pageTitle = 'Quản lý người dùng';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_only();
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $role = isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : '';

    try {
        if ($userId === false || !in_array($role, ['customer', 'admin'], true)) {
            throw new RuntimeException('Thông tin phân quyền không hợp lệ.');
        }
        if ($userId === (int) current_user()['id']) {
            throw new RuntimeException('Bạn không thể tự thay đổi quyền của tài khoản đang đăng nhập.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        $targetStatement = $pdo->prepare('SELECT id, role FROM users WHERE id = :id FOR UPDATE');
        $targetStatement->execute([':id' => $userId]);
        $target = $targetStatement->fetch();
        if ($target === false) {
            throw new RuntimeException('Không tìm thấy người dùng.');
        }
        if ($target['role'] === 'admin' && $role === 'customer') {
            $adminLocks = $pdo->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
            $adminCount = count($adminLocks->fetchAll(PDO::FETCH_COLUMN));
            if ($adminCount <= 1) {
                throw new RuntimeException('Không thể hạ quyền quản trị viên cuối cùng.');
            }
        }
        $update = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $update->execute([':role' => $role, ':id' => $userId]);
        $pdo->commit();
        set_flash('success', 'Đã cập nhật quyền người dùng.');
    } catch (RuntimeException $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('warning', $exception->getMessage());
    } catch (PDOException $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('TechZone user role update failed: ' . $exception->getMessage());
        set_flash('danger', 'Không thể cập nhật quyền người dùng.');
    }
    redirect(BASE_URL . '/admin/users.php');
}

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentPage = $requestedPage === false ? 1 : $requestedPage;
$perPage = 20;
$users = [];
$totalPages = 1;
$databaseError = false;
$selectedUser = null;
$selectedOrders = [];

try {
    $pdo = db();
    $where = '';
    $parameters = [];
    if ($search !== '') {
        $where = ' WHERE full_name LIKE :name OR email LIKE :email OR phone LIKE :phone';
        $pattern = '%' . $search . '%';
        $parameters = [':name' => $pattern, ':email' => $pattern, ':phone' => $pattern];
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM users' . $where);
    $count->execute($parameters);
    $totalPages = max(1, (int) ceil((int) $count->fetchColumn() / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $statement = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.created_at,
                COUNT(o.id) AS order_count
         FROM users u
         LEFT JOIN orders o ON o.user_id = u.id'
        . ($search !== '' ? ' WHERE u.full_name LIKE :name OR u.email LIKE :email OR u.phone LIKE :phone' : '')
        . ' GROUP BY u.id, u.full_name, u.email, u.phone, u.role, u.created_at
           ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $key => $value) {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($currentPage - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $users = $statement->fetchAll();

    $selectedUserId = filter_var($_GET['user'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($selectedUserId !== false && $selectedUserId !== null) {
        $userStatement = $pdo->prepare('SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = :id');
        $userStatement->execute([':id' => $selectedUserId]);
        $selectedUser = $userStatement->fetch() ?: null;
        if ($selectedUser !== null) {
            $ordersStatement = $pdo->prepare(
                'SELECT id, order_code, total_amount, order_status, payment_status, created_at
                 FROM orders WHERE user_id = :id ORDER BY created_at DESC LIMIT 20'
            );
            $ordersStatement->execute([':id' => $selectedUserId]);
            $selectedOrders = $ordersStatement->fetchAll();
        }
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone admin users query failed: ' . $exception->getMessage());
    $databaseError = true;
}
require __DIR__ . '/admin-header.php';
?>
<div class="admin-page-heading"><div><h1>Người dùng</h1><p>Xem khách hàng và quản lý quyền truy cập quản trị.</p></div></div>
<?php if ($databaseError): ?>
    <div class="alert alert-danger">Không thể tải người dùng. Vui lòng kiểm tra cơ sở dữ liệu.</div>
<?php else: ?>
    <section class="admin-panel">
        <form method="get" class="admin-search-form">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Tìm theo tên, email hoặc điện thoại">
            <button class="btn btn-outline-primary" type="submit">Tìm kiếm</button>
        </form>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead><tr><th>Người dùng</th><th>Liên hệ</th><th>Đơn hàng</th><th>Vai trò</th><th>Ngày tham gia</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?= e($user['full_name']) ?></strong><small>#<?= (int) $user['id'] ?></small></td>
                        <td><?= e($user['email']) ?><small><?= e($user['phone']) ?></small></td>
                        <td><?= (int) $user['order_count'] ?></td>
                        <td><span class="badge text-bg-<?= $user['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= $user['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?></span></td>
                        <td><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></td>
                        <td>
                            <div class="admin-row-actions">
                                <a class="btn btn-sm btn-outline-primary" href="?<?= e(http_build_query(array_filter(['q' => $search, 'page' => $currentPage, 'user' => (int) $user['id']], static fn (mixed $value): bool => $value !== ''))) ?>">Chi tiết</a>
                                <?php if ((int) $user['id'] !== (int) current_user()['id']): ?>
                                    <form method="post">
                                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="role" value="<?= $user['role'] === 'admin' ? 'customer' : 'admin' ?>">
                                        <button class="btn btn-sm btn-outline-<?= $user['role'] === 'admin' ? 'warning' : 'success' ?>" type="submit" onclick="return confirm('Thay đổi quyền của người dùng này?')"><?= $user['role'] === 'admin' ? 'Hạ quyền' : 'Cấp admin' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?><tr><td colspan="6" class="text-center py-4">Không tìm thấy người dùng.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?><nav><ul class="pagination justify-content-center"><?php for ($page = 1; $page <= $totalPages; $page++): ?><li class="page-item <?= $page === $currentPage ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(['q' => $search, 'page' => $page])) ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
    </section>
    <?php if (is_array($selectedUser)): ?>
        <section class="admin-panel mt-4">
            <div class="admin-panel-heading"><h2>Lịch sử đơn hàng: <?= e($selectedUser['full_name']) ?></h2><a href="<?= e(BASE_URL) ?>/admin/users.php">Đóng</a></div>
            <p><?= e($selectedUser['email']) ?> · <?= e($selectedUser['phone']) ?> · Tham gia <?= e(date('d/m/Y', strtotime($selectedUser['created_at']))) ?></p>
            <?php if ($selectedOrders === []): ?><div class="empty-state">Khách hàng chưa có đơn hàng.</div><?php else: ?>
                <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th></tr></thead><tbody><?php foreach ($selectedOrders as $order): ?><tr><td><a href="<?= e(BASE_URL) ?>/admin/orders.php?order=<?= (int) $order['id'] ?>"><?= e($order['order_code']) ?></a></td><td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td><td><?= e(format_vnd($order['total_amount'])) ?></td><td><?= e($order['order_status']) ?></td><td><?= e($order['payment_status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/admin-footer.php'; ?>
