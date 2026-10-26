<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-functions.php';

$pageTitle = 'Dashboard';
$metrics = ['products' => 0, 'orders' => 0, 'customers' => 0, 'revenue' => 0];
$recentOrders = [];
$revenueByDay = [];
$databaseError = false;

try {
    $pdo = db();
    $metrics['products'] = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $metrics['orders'] = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $metrics['customers'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $metrics['revenue'] = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount), 0) FROM orders
         WHERE order_status = 'delivered' AND payment_status = 'paid'"
    )->fetchColumn();
    $recentOrders = $pdo->query(
        'SELECT o.id, o.order_code, o.recipient_name, o.total_amount, o.order_status, o.created_at,
                u.email
         FROM orders o LEFT JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC LIMIT 8'
    )->fetchAll();
    $revenueByDay = $pdo->query(
        "SELECT DATE(created_at) AS revenue_day, SUM(total_amount) AS revenue
         FROM orders
         WHERE order_status = 'delivered' AND payment_status = 'paid'
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)
         ORDER BY revenue_day"
    )->fetchAll();
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone admin dashboard query failed: ' . $exception->getMessage());
    $databaseError = true;
}

$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao',
    'delivered' => 'Đã giao',
    'cancelled' => 'Đã hủy',
];
require __DIR__ . '/admin-header.php';
?>
<div class="admin-page-heading"><div><h1>Dashboard</h1><p>Tổng quan hoạt động cửa hàng TechZone.</p></div></div>
<?php if ($databaseError): ?>
    <div class="alert alert-danger" role="alert">Không thể tải dashboard. Hãy kiểm tra MySQL và import file database.sql trước.</div>
<?php else: ?>
    <div class="admin-metrics">
        <article><span>Sản phẩm đang bán</span><strong><?= $metrics['products'] ?></strong><a href="<?= e(BASE_URL) ?>/admin/products.php">Quản lý sản phẩm →</a></article>
        <article><span>Tổng đơn hàng</span><strong><?= $metrics['orders'] ?></strong><a href="<?= e(BASE_URL) ?>/admin/orders.php">Quản lý đơn hàng →</a></article>
        <article><span>Khách hàng</span><strong><?= $metrics['customers'] ?></strong><a href="<?= e(BASE_URL) ?>/admin/users.php">Quản lý người dùng →</a></article>
        <article><span>Doanh thu đã thanh toán</span><strong class="metric-revenue"><?= e(format_vnd($metrics['revenue'])) ?></strong><small>Đơn đã giao và đã thanh toán</small></article>
    </div>
    <div class="admin-content-grid">
        <section class="admin-panel">
            <div class="admin-panel-heading"><h2>Đơn hàng gần đây</h2><a href="<?= e(BASE_URL) ?>/admin/orders.php">Xem tất cả</a></div>
            <?php if ($recentOrders === []): ?>
                <div class="empty-state">Chưa có đơn hàng.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table admin-table">
                        <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><a href="<?= e(BASE_URL) ?>/admin/orders.php?order=<?= (int) $order['id'] ?>"><?= e($order['order_code']) ?></a></td>
                                <td><?= e($order['recipient_name']) ?><small><?= e($order['email'] ?? 'Khách vãng lai') ?></small></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                                <td><?= e(format_vnd($order['total_amount'])) ?></td>
                                <td><span class="order-status-badge status-<?= e($order['order_status']) ?>"><?= e($statusLabels[$order['order_status']] ?? $order['order_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <section class="admin-panel">
            <div class="admin-panel-heading"><h2>Doanh thu 7 ngày</h2></div>
            <?php if ($revenueByDay === []): ?>
                <div class="empty-state">Chưa có doanh thu từ đơn hợp lệ.</div>
            <?php else: ?>
                <?php $maxRevenue = max(array_map(static fn (array $row): float => (float) $row['revenue'], $revenueByDay)); ?>
                <div class="revenue-list">
                    <?php foreach ($revenueByDay as $day): ?>
                        <div class="revenue-item">
                            <span><?= e(date('d/m', strtotime($day['revenue_day']))) ?></span>
                            <div class="revenue-bar-track"><span style="width: <?= $maxRevenue > 0 ? (int) round((float) $day['revenue'] / $maxRevenue * 100) : 0 ?>%"></span></div>
                            <strong><?= e(format_vnd($day['revenue'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="admin-muted">Chỉ tính đơn đã giao và đã thanh toán.</p>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/admin-footer.php'; ?>
