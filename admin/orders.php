<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';
$pageTitle = 'Quản lý đơn hàng';
$validStatuses = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao',
    'delivered' => 'Đã giao',
    'cancelled' => 'Đã hủy',
];
$allowedTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['shipping', 'cancelled'],
    'shipping' => ['delivered', 'cancelled'],
    'delivered' => [],
    'cancelled' => [],
];
$paymentLabels = ['unpaid' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán', 'refunded' => 'Đã hoàn tiền'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_only();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($orderId === false) {
        set_flash('danger', 'Mã đơn hàng không hợp lệ.');
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $orderStatement = $pdo->prepare('SELECT id, order_status, payment_status FROM orders WHERE id = :id FOR UPDATE');
            $orderStatement->execute([':id' => $orderId]);
            $order = $orderStatement->fetch();
            if ($order === false) {
                throw new RuntimeException('Không tìm thấy đơn hàng.');
            }

            if ($action === 'status') {
                $newStatus = isset($_POST['order_status']) && is_string($_POST['order_status']) ? $_POST['order_status'] : '';
                if (!in_array($newStatus, $allowedTransitions[$order['order_status']] ?? [], true)) {
                    throw new RuntimeException('Trạng thái đơn hàng không hợp lệ hoặc không thể chuyển từ trạng thái hiện tại.');
                }
                if ($newStatus === 'cancelled') {
                    $itemsStatement = $pdo->prepare(
                        'SELECT product_id, quantity FROM order_items WHERE order_id = :order_id AND product_id IS NOT NULL'
                    );
                    $itemsStatement->execute([':order_id' => $orderId]);
                    $stockRestore = $pdo->prepare('UPDATE products SET stock = stock + :quantity WHERE id = :id');
                    foreach ($itemsStatement->fetchAll() as $item) {
                        $stockRestore->execute([
                            ':quantity' => (int) $item['quantity'],
                            ':id' => (int) $item['product_id'],
                        ]);
                    }
                }
                $update = $pdo->prepare('UPDATE orders SET order_status = :status WHERE id = :id');
                $update->execute([':status' => $newStatus, ':id' => $orderId]);
                set_flash('success', $newStatus === 'cancelled'
                    ? 'Đơn hàng đã hủy và tồn kho được hoàn lại.'
                    : 'Đã cập nhật trạng thái đơn hàng.');
            } elseif ($action === 'payment') {
                $newPaymentStatus = isset($_POST['payment_status']) && is_string($_POST['payment_status']) ? $_POST['payment_status'] : '';
                $paymentTransitions = [
                    'unpaid' => ['paid'],
                    'paid' => ['refunded'],
                    'refunded' => [],
                ];
                if ($newPaymentStatus === $order['payment_status']) {
                    throw new RuntimeException('Trạng thái thanh toán không thay đổi.');
                }
                if (!in_array($newPaymentStatus, $paymentTransitions[$order['payment_status']] ?? [], true)) {
                    throw new RuntimeException('Trạng thái thanh toán không hợp lệ.');
                }
                if ($newPaymentStatus === 'paid' && $order['order_status'] !== 'delivered') {
                    throw new RuntimeException('Chỉ đánh dấu đã thanh toán sau khi đơn hàng đã giao và xác nhận nhận tiền.');
                }
                $update = $pdo->prepare('UPDATE orders SET payment_status = :status WHERE id = :id');
                $update->execute([':status' => $newPaymentStatus, ':id' => $orderId]);
                set_flash('success', 'Đã cập nhật trạng thái thanh toán.');
            } else {
                throw new RuntimeException('Thao tác đơn hàng không hợp lệ.');
            }
            $pdo->commit();
        } catch (RuntimeException $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('warning', $exception->getMessage());
        } catch (PDOException $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('TechZone order status update failed: ' . $exception->getMessage());
            set_flash('danger', 'Không thể cập nhật đơn hàng. Vui lòng thử lại.');
        }
    }
    redirect(BASE_URL . '/admin/orders.php');
}

$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentPage = $requestedPage === false ? 1 : $requestedPage;
$filterStatus = isset($_GET['status']) && is_string($_GET['status']) && in_array($_GET['status'], $validStatuses, true)
    ? $_GET['status']
    : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$selectedOrderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$orders = [];
$orderDetail = null;
$orderDetailItems = [];
$totalPages = 1;
$databaseError = false;

try {
    $pdo = db();
    $where = [];
    $parameters = [];
    if ($filterStatus !== '') {
        $where[] = 'o.order_status = :status';
        $parameters[':status'] = $filterStatus;
    }
    if ($search !== '') {
        $where[] = '(o.order_code LIKE :code OR o.recipient_name LIKE :recipient OR o.phone LIKE :phone)';
        $pattern = '%' . $search . '%';
        $parameters[':code'] = $pattern;
        $parameters[':recipient'] = $pattern;
        $parameters[':phone'] = $pattern;
    }
    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*) FROM orders o' . $whereSql);
    $count->execute($parameters);
    $totalPages = max(1, (int) ceil((int) $count->fetchColumn() / 15));
    $currentPage = min($currentPage, $totalPages);
    $statement = $pdo->prepare(
        'SELECT o.id, o.order_code, o.recipient_name, o.phone, o.total_amount, o.payment_method,
                o.payment_status, o.order_status, o.created_at
         FROM orders o' . $whereSql . ' ORDER BY o.created_at DESC LIMIT 15 OFFSET :offset'
    );
    foreach ($parameters as $key => $value) {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':offset', ($currentPage - 1) * 15, PDO::PARAM_INT);
    $statement->execute();
    $orders = $statement->fetchAll();

    if ($selectedOrderId !== false && $selectedOrderId !== null) {
        $detail = $pdo->prepare(
            'SELECT o.*, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1'
        );
        $detail->execute([':id' => $selectedOrderId]);
        $orderDetail = $detail->fetch() ?: null;
        if ($orderDetail !== null) {
            $itemStatement = $pdo->prepare(
                'SELECT product_name, price, quantity, subtotal FROM order_items WHERE order_id = :id ORDER BY id'
            );
            $itemStatement->execute([':id' => $selectedOrderId]);
            $orderDetailItems = $itemStatement->fetchAll();
        }
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone admin orders query failed: ' . $exception->getMessage());
    $databaseError = true;
}

require __DIR__ . '/admin-header.php';
?>
<div class="admin-page-heading"><div><h1>Đơn hàng</h1><p>Cập nhật trạng thái và xác nhận thanh toán thủ công.</p></div></div>
<?php if ($databaseError): ?>
    <div class="alert alert-danger">Không thể tải đơn hàng. Vui lòng kiểm tra cơ sở dữ liệu.</div>
<?php else: ?>
    <section class="admin-panel mb-4">
        <form method="get" class="admin-search-form">
            <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Mã đơn, tên người nhận hoặc số điện thoại">
            <select class="form-select" name="status"><option value="">Tất cả trạng thái</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $filterStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <button class="btn btn-outline-primary" type="submit">Lọc</button>
        </form>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead><tr><th>Mã đơn</th><th>Người nhận</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?= e($order['order_code']) ?></strong><small><?= e($order['phone']) ?></small></td>
                        <td><?= e($order['recipient_name']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                        <td><?= e(format_vnd($order['total_amount'])) ?></td>
                        <td><span class="order-status-badge status-<?= e($order['order_status']) ?>"><?= e($statusLabels[$order['order_status']] ?? $order['order_status']) ?></span></td>
                        <td><?= e($paymentLabels[$order['payment_status']] ?? $order['payment_status']) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="?<?= e(http_build_query(array_filter(['q' => $search, 'status' => $filterStatus, 'page' => $currentPage, 'order' => (int) $order['id']], static fn (mixed $value): bool => $value !== ''))) ?>">Chi tiết</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($orders === []): ?><tr><td colspan="7" class="text-center py-4">Không tìm thấy đơn hàng.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?><nav><ul class="pagination justify-content-center"><?php for ($page = 1; $page <= $totalPages; $page++): ?><li class="page-item <?= $page === $currentPage ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(array_filter(['q' => $search, 'status' => $filterStatus, 'page' => $page], static fn (mixed $value): bool => $value !== ''))) ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
    </section>
    <?php if (is_array($orderDetail)): ?>
        <section class="admin-panel">
            <div class="admin-panel-heading"><h2>Đơn <?= e($orderDetail['order_code']) ?></h2><a href="<?= e(BASE_URL) ?>/admin/orders.php">Đóng</a></div>
            <div class="admin-order-details">
                <div><strong>Người nhận</strong><span><?= e($orderDetail['recipient_name']) ?> · <?= e($orderDetail['phone']) ?></span></div>
                <div><strong>Email tài khoản</strong><span><?= e($orderDetail['email'] ?? 'Khách vãng lai') ?></span></div>
                <div><strong>Địa chỉ</strong><span><?= e($orderDetail['address']) ?></span></div>
                <div><strong>Ghi chú</strong><span><?= e($orderDetail['note'] ?: 'Không có') ?></span></div>
            </div>
            <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead><tbody><?php foreach ($orderDetailItems as $item): ?><tr><td><?= e($item['product_name']) ?></td><td><?= e(format_vnd($item['price'])) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= e(format_vnd($item['subtotal'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="admin-order-total">Tổng cộng: <strong><?= e(format_vnd($orderDetail['total_amount'])) ?></strong></div>
            <div class="admin-order-actions">
                <form method="post" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="order_id" value="<?= (int) $orderDetail['id'] ?>"><label class="form-label" for="new-status">Chuyển trạng thái</label><select class="form-select" name="order_status" id="new-status" required><option value="">Chọn trạng thái tiếp theo</option><?php foreach ($allowedTransitions[$orderDetail['order_status']] ?? [] as $nextStatus): ?><option value="<?= e($nextStatus) ?>"><?= e($statusLabels[$nextStatus]) ?></option><?php endforeach; ?></select><button class="btn btn-techzone" type="submit" <?= empty($allowedTransitions[$orderDetail['order_status']] ?? []) ? 'disabled' : '' ?>>Cập nhật đơn</button></form>
                <form method="post" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="payment"><input type="hidden" name="order_id" value="<?= (int) $orderDetail['id'] ?>"><label class="form-label" for="new-payment-status">Trạng thái thanh toán</label><select class="form-select" name="payment_status" id="new-payment-status"><?php foreach ($paymentLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $orderDetail['payment_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary" type="submit">Cập nhật thanh toán</button><small>Chỉ xác nhận đã thanh toán sau khi cửa hàng thực nhận tiền.</small></form>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/admin-footer.php'; ?>
