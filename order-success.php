<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Đặt hàng thành công';
$order = null;
$items = [];
$databaseError = false;
$orderCode = isset($_SESSION['last_order_code']) && is_string($_SESSION['last_order_code'])
    ? $_SESSION['last_order_code']
    : '';

if ($orderCode !== '') {
    try {
        $user = current_user();
        if ($user !== null) {
            $statement = db()->prepare(
                'SELECT id, order_code, recipient_name, phone, address, subtotal, shipping_fee, total_amount,
                        payment_method, payment_status, order_status, created_at
                 FROM orders WHERE order_code = :order_code AND user_id = :user_id LIMIT 1'
            );
            $statement->execute([':order_code' => $orderCode, ':user_id' => (int) $user['id']]);
        } else {
            $statement = db()->prepare(
                'SELECT id, order_code, recipient_name, phone, address, subtotal, shipping_fee, total_amount,
                        payment_method, payment_status, order_status, created_at
                 FROM orders WHERE order_code = :order_code AND user_id IS NULL LIMIT 1'
            );
            $statement->execute([':order_code' => $orderCode]);
        }
        $order = $statement->fetch() ?: null;
        if ($order !== null) {
            $itemStatement = db()->prepare(
                'SELECT product_name, price, quantity, subtotal
                 FROM order_items WHERE order_id = :order_id ORDER BY id'
            );
            $itemStatement->execute([':order_id' => (int) $order['id']]);
            $items = $itemStatement->fetchAll();
        }
    } catch (PDOException | RuntimeException $exception) {
        error_log('TechZone order confirmation query failed: ' . $exception->getMessage());
        $databaseError = true;
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container order-success-container">
        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">Đơn hàng đã được gửi, nhưng chưa thể tải thông tin xác nhận. Hãy đăng nhập để xem lịch sử đơn hàng hoặc liên hệ cửa hàng.</div>
        <?php elseif ($order === null): ?>
            <div class="empty-state"><h1 class="h4">Không tìm thấy thông tin xác nhận</h1><p>Vui lòng kiểm tra lịch sử mua hàng hoặc quay lại trang chủ.</p><a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/index.php">Về trang chủ</a></div>
        <?php else: ?>
            <div class="success-mark" aria-hidden="true">✓</div>
            <h1>Đặt hàng thành công</h1>
            <p class="success-intro">TechZone đã ghi nhận đơn hàng. Nhân viên sẽ liên hệ để xác nhận trước khi giao hàng.</p>
            <div class="order-confirmation-card">
                <div class="order-code">Mã đơn hàng <strong><?= e($order['order_code']) ?></strong></div>
                <div class="confirmation-row"><span>Trạng thái đơn hàng</span><strong>Chờ xác nhận</strong></div>
                <div class="confirmation-row"><span>Thanh toán</span><strong><?= $order['payment_method'] === 'cod' ? 'Thanh toán khi nhận hàng' : 'Chuyển khoản thủ công · Chưa thanh toán' ?></strong></div>
                <div class="confirmation-row"><span>Người nhận</span><strong><?= e($order['recipient_name']) ?> · <?= e($order['phone']) ?></strong></div>
                <div class="confirmation-row"><span>Địa chỉ</span><strong><?= e($order['address']) ?></strong></div>
                <div class="confirmation-items">
                    <?php foreach ($items as $item): ?>
                        <div><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= e(format_vnd($item['subtotal'])) ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <div class="confirmation-row"><span>Tạm tính</span><strong><?= e(format_vnd($order['subtotal'])) ?></strong></div>
                <div class="confirmation-row"><span>Phí vận chuyển</span><strong><?= e(format_vnd($order['shipping_fee'])) ?></strong></div>
                <div class="confirmation-total"><span>Tổng cộng</span><strong><?= e(format_vnd($order['total_amount'])) ?></strong></div>
            </div>
            <div class="success-actions">
                <?php if (current_user() !== null): ?><a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/orders-history.php">Xem lịch sử đơn hàng</a><?php endif; ?>
                <a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/products.php">Tiếp tục mua sắm</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
