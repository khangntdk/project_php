<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Lịch sử mua hàng';
$user = current_user();
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentPage = $requestedPage === false ? 1 : $requestedPage;
$perPage = 10;
$orders = [];
$orderItems = [];
$totalPages = 1;
$databaseError = false;
$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao',
    'delivered' => 'Đã giao',
    'cancelled' => 'Đã hủy',
];
$paymentLabels = [
    'unpaid' => 'Chưa thanh toán',
    'paid' => 'Đã thanh toán',
    'refunded' => 'Đã hoàn tiền',
];

try {
    $pdo = db();
    $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
    $count->execute([':user_id' => (int) $user['id']]);
    $totalOrders = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($totalOrders / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    $statement = $pdo->prepare(
        'SELECT id, order_code, recipient_name, subtotal, shipping_fee, total_amount,
                payment_method, payment_status, order_status, created_at
         FROM orders WHERE user_id = :user_id
         ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
    );
    $statement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $orders = $statement->fetchAll();
    if ($orders !== []) {
        $itemStatement = $pdo->prepare(
            'SELECT product_name, price, quantity, subtotal
             FROM order_items WHERE order_id = :order_id ORDER BY id'
        );
        foreach ($orders as $order) {
            $itemStatement->execute([':order_id' => (int) $order['id']]);
            $orderItems[(int) $order['id']] = $itemStatement->fetchAll();
        }
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone order history query failed: ' . $exception->getMessage());
    $databaseError = true;
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container account-layout">
        <aside class="account-sidebar">
            <h1>Tài khoản</h1>
            <p><?= e($user['email'] ?? '') ?></p>
            <a class="account-nav-link" href="<?= e(BASE_URL) ?>/profile.php">Thông tin cá nhân</a>
            <a class="account-nav-link active" href="<?= e(BASE_URL) ?>/orders-history.php">Lịch sử mua hàng</a>
        </aside>
        <div class="account-content">
            <div class="section-heading"><div><h2>Lịch sử mua hàng</h2><p>Theo dõi trạng thái và chi tiết đơn hàng.</p></div></div>
            <?php if ($databaseError): ?>
                <div class="alert alert-danger" role="alert">Không thể tải lịch sử đơn hàng. Vui lòng thử lại sau.</div>
            <?php elseif ($orders === []): ?>
                <div class="empty-state"><h3 class="h5">Bạn chưa có đơn hàng nào</h3><p>Đơn hàng đã đặt sẽ xuất hiện tại đây.</p><a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/products.php">Khám phá sản phẩm</a></div>
            <?php else: ?>
                <div class="order-history-list">
                    <?php foreach ($orders as $order): ?>
                        <article class="history-order">
                            <header class="history-order-header">
                                <div><span class="history-order-label">Mã đơn hàng</span><strong><?= e($order['order_code']) ?></strong></div>
                                <span class="order-status-badge status-<?= e($order['order_status']) ?>"><?= e($statusLabels[$order['order_status']] ?? $order['order_status']) ?></span>
                            </header>
                            <div class="history-order-meta">
                                <span>Đặt ngày <?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></span>
                                <span><?= $order['payment_method'] === 'cod' ? 'Thanh toán khi nhận hàng' : 'Chuyển khoản thủ công' ?></span>
                                <span>Thanh toán: <?= e($paymentLabels[$order['payment_status']] ?? $order['payment_status']) ?></span>
                            </div>
                            <div class="history-order-items">
                                <?php foreach ($orderItems[(int) $order['id']] ?? [] as $item): ?>
                                    <div><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= e(format_vnd($item['subtotal'])) ?></strong></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="history-order-total"><span>Tổng cộng</span><strong><?= e(format_vnd($order['total_amount'])) ?></strong></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4" aria-label="Phân trang lịch sử đơn hàng">
                        <ul class="pagination justify-content-center">
                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>"><a class="page-link" href="<?= e(BASE_URL) ?>/orders-history.php?page=<?= $page ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a></li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
