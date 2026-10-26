<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

function cart_read_items(array $cart): array
{
    $items = [];
    try {
        $pdo = db();
        $statement = $pdo->prepare(
            'SELECT id, name, slug, sku, price, sale_price, stock, image
             FROM products WHERE id = :id AND status = :status LIMIT 1'
        );

        foreach ($cart as $id => $quantity) {
            $productId = filter_var((string) $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $amount = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($productId === false || $amount === false) {
                continue;
            }
            $statement->execute([':id' => $productId, ':status' => 'active']);
            $product = $statement->fetch();
            if ($product !== false) {
                $product['quantity'] = $amount;
                $product['unit_price'] = $product['sale_price'] !== null && (float) $product['sale_price'] > 0
                    ? (float) $product['sale_price']
                    : (float) $product['price'];
                $product['line_total'] = $product['unit_price'] * $amount;
                $items[] = $product;
            }
        }
    } catch (PDOException | RuntimeException $exception) {
        error_log('TechZone cart read failed: ' . $exception->getMessage());
        throw new RuntimeException('Không thể tải giỏ hàng do lỗi cơ sở dữ liệu.', 0, $exception);
    }

    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        unset($_SESSION['checkout_items']);
        set_flash('success', 'Giỏ hàng đã được làm trống.');
        redirect(BASE_URL . '/cart.php');
    }

    if (in_array($action, ['add', 'buy_now', 'update', 'remove'], true)) {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($productId === false) {
            set_flash('danger', 'Sản phẩm không hợp lệ.');
            redirect(BASE_URL . '/cart.php');
        }

        try {
            $statement = db()->prepare(
                'SELECT id, name, stock FROM products WHERE id = :id AND status = :status LIMIT 1'
            );
            $statement->execute([':id' => $productId, ':status' => 'active']);
            $product = $statement->fetch();

            if ($product === false) {
                set_flash('danger', 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.');
                redirect(BASE_URL . '/cart.php');
            }

            if ($action === 'remove') {
                unset($cart[$productId]);
                if (isset($_SESSION['checkout_items'][$productId])) {
                    unset($_SESSION['checkout_items'][$productId]);
                    if ($_SESSION['checkout_items'] === []) {
                        unset($_SESSION['checkout_items']);
                    }
                }
                set_flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
            } else {
                $requestedQuantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
                if ($requestedQuantity === false) {
                    set_flash('danger', 'Số lượng phải từ 1 đến 100.');
                    redirect(BASE_URL . '/cart.php');
                }

                if ($action === 'update' || $action === 'buy_now') {
                    $newQuantity = $requestedQuantity;
                } else {
                    $newQuantity = (int) ($cart[$productId] ?? 0) + $requestedQuantity;
                }

                if ($newQuantity > (int) $product['stock']) {
                    set_flash('warning', 'Số lượng yêu cầu vượt quá tồn kho hiện tại.');
                    redirect(BASE_URL . '/cart.php');
                }

                if ($action === 'buy_now') {
                    $cart[$productId] = min(
                        max((int) ($cart[$productId] ?? 0), $requestedQuantity),
                        (int) $product['stock']
                    );
                    $_SESSION['checkout_items'] = [$productId => $requestedQuantity];
                    $_SESSION['cart'] = $cart;
                    redirect(BASE_URL . '/checkout.php');
                }
                $cart[$productId] = $newQuantity;
                set_flash('success', 'Đã cập nhật giỏ hàng.');
            }

            $_SESSION['cart'] = $cart;
        } catch (PDOException | RuntimeException $exception) {
            error_log('TechZone cart update failed: ' . $exception->getMessage());
            set_flash('danger', 'Không thể cập nhật giỏ hàng do lỗi cơ sở dữ liệu. Vui lòng thử lại.');
        }
        redirect(BASE_URL . '/cart.php');
    }

    set_flash('danger', 'Thao tác giỏ hàng không hợp lệ.');
    redirect(BASE_URL . '/cart.php');
}

$pageTitle = 'Giỏ hàng';
$databaseError = false;
$items = [];
$cartTotal = 0.0;
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

try {
    $items = cart_read_items($cart);
    $validCart = [];
    foreach ($items as $item) {
        $validCart[(int) $item['id']] = min((int) $item['quantity'], (int) $item['stock']);
        $cartTotal += (float) $item['unit_price'] * $validCart[(int) $item['id']];
    }
    $_SESSION['cart'] = array_filter($validCart, static fn (int $quantity): bool => $quantity > 0);
    $items = cart_read_items($_SESSION['cart']);
    $cartTotal = array_sum(array_map(static fn (array $item): float => (float) $item['line_total'], $items));
} catch (RuntimeException $exception) {
    $databaseError = true;
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading"><div><h1>Giỏ hàng</h1><p>Kiểm tra sản phẩm và số lượng trước khi đặt hàng.</p></div></div>
        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">Không thể tải giỏ hàng. Vui lòng kiểm tra kết nối cơ sở dữ liệu.</div>
        <?php elseif ($items === []): ?>
            <div class="empty-state">
                <h2 class="h5">Giỏ hàng của bạn đang trống</h2>
                <p>Khám phá các thiết bị công nghệ và thêm sản phẩm bạn yêu thích.</p>
                <a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/products.php">Tiếp tục mua sắm</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php foreach ($items as $item): ?>
                        <article class="cart-item">
                            <a class="cart-item-image" href="<?= e(BASE_URL) ?>/product-detail.php?slug=<?= rawurlencode($item['slug']) ?>">
                                <?php if (is_string($item['image']) && $item['image'] !== '' && !str_contains($item['image'], '..') && !str_contains($item['image'], '://')): ?>
                                    <img src="<?= e(BASE_URL . '/' . ltrim($item['image'], '/')) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
                                <?php else: ?><span class="product-image-placeholder" aria-hidden="true">TZ</span><?php endif; ?>
                            </a>
                            <div class="cart-item-main">
                                <h2><a href="<?= e(BASE_URL) ?>/product-detail.php?slug=<?= rawurlencode($item['slug']) ?>"><?= e($item['name']) ?></a></h2>
                                <div class="cart-item-sku">Mã: <?= e($item['sku']) ?> · Còn <?= (int) $item['stock'] ?></div>
                                <div class="product-price"><?= e(format_vnd($item['unit_price'])) ?></div>
                                <?php if ($item['unit_price'] < (float) $item['price']): ?><del class="product-old-price"><?= e(format_vnd($item['price'])) ?></del><?php endif; ?>
                            </div>
                            <form method="post" action="<?= e(BASE_URL) ?>/cart.php" class="cart-quantity-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                <label class="visually-hidden" for="quantity-<?= (int) $item['id'] ?>">Số lượng <?= e($item['name']) ?></label>
                                <input id="quantity-<?= (int) $item['id'] ?>" class="form-control" type="number" name="quantity" min="1" max="<?= max(1, (int) $item['stock']) ?>" value="<?= (int) $item['quantity'] ?>">
                                <button class="btn btn-outline-primary btn-sm" type="submit">Cập nhật</button>
                            </form>
                            <div class="cart-line-total"><?= e(format_vnd((float) $item['unit_price'] * (int) $item['quantity'])) ?></div>
                            <form method="post" action="<?= e(BASE_URL) ?>/cart.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-link text-danger" type="submit">Xóa</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
                <aside class="cart-summary">
                    <h2>Tóm tắt đơn hàng</h2>
                    <div class="summary-line"><span>Tạm tính</span><strong><?= e(format_vnd($cartTotal)) ?></strong></div>
                    <div class="summary-line"><span>Phí vận chuyển</span><span>Được xác nhận khi đặt hàng</span></div>
                    <div class="summary-total"><span>Tổng tiền hàng</span><strong><?= e(format_vnd($cartTotal)) ?></strong></div>
                    <a class="btn btn-techzone btn-lg w-100" href="<?= e(BASE_URL) ?>/checkout.php?all=1">Tiến hành đặt hàng</a>
                    <form method="post" action="<?= e(BASE_URL) ?>/cart.php" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                        <button class="btn btn-outline-secondary w-100" type="submit">Xóa toàn bộ giỏ hàng</button>
                    </form>
                    <p class="cart-note">Giá và tồn kho sẽ được xác nhận lại khi đặt hàng.</p>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
