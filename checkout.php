<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Đặt hàng';
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
$checkoutSelection = (!isset($_GET['all']) || $_GET['all'] !== '1')
    && isset($_SESSION['checkout_items']) && is_array($_SESSION['checkout_items'])
    ? $_SESSION['checkout_items']
    : null;
$checkoutCart = $checkoutSelection ?? $cart;
$items = [];
$subtotal = 0.0;
$shippingFee = 0.0;
$errors = [];
$databaseError = false;
$recipientName = current_user()['full_name'] ?? '';
$phone = current_user()['phone'] ?? '';
$address = '';
$note = '';
$paymentMethod = 'cod';

try {
    $pdo = db();
    $productStatement = $pdo->prepare(
        'SELECT id, name, slug, sku, price, sale_price, stock, image
         FROM products WHERE id = :id AND status = :status LIMIT 1'
    );
    foreach ($checkoutCart as $id => $quantity) {
        $productId = filter_var((string) $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $amount = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($productId === false || $amount === false) {
            continue;
        }
        $productStatement->execute([':id' => $productId, ':status' => 'active']);
        $product = $productStatement->fetch();
        if ($product === false) {
            $errors[] = 'Một sản phẩm trong giỏ đã ngừng kinh doanh. Vui lòng cập nhật giỏ hàng.';
            continue;
        }
        if ($amount > (int) $product['stock']) {
            $errors[] = 'Số lượng sản phẩm "' . $product['name'] . '" vượt quá tồn kho hiện tại.';
            continue;
        }
        $product['quantity'] = $amount;
        $product['unit_price'] = $product['sale_price'] !== null && (float) $product['sale_price'] > 0
            ? (float) $product['sale_price']
            : (float) $product['price'];
        $product['line_total'] = $product['unit_price'] * $amount;
        $items[] = $product;
        $subtotal += $product['line_total'];
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone checkout cart query failed: ' . $exception->getMessage());
    $databaseError = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$databaseError) {
    verify_csrf_token();
    $recipientName = isset($_POST['recipient_name']) && is_string($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
    $phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) && is_string($_POST['address']) ? trim($_POST['address']) : '';
    $note = isset($_POST['note']) && is_string($_POST['note']) ? trim($_POST['note']) : '';
    $paymentMethod = isset($_POST['payment_method']) && is_string($_POST['payment_method']) ? $_POST['payment_method'] : '';

    if ($recipientName === '' || strlen($recipientName) > 150) {
        $errors[] = 'Vui lòng nhập tên người nhận (tối đa 150 ký tự).';
    }
    if (!preg_match('/^[0-9+().\-\s]{8,30}$/', $phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại hợp lệ.';
    }
    if ($address === '' || strlen($address) > 500) {
        $errors[] = 'Vui lòng nhập địa chỉ giao hàng (tối đa 500 ký tự).';
    }
    if (strlen($note) > 2000) {
        $errors[] = 'Ghi chú không được vượt quá 2.000 ký tự.';
    }
    if (!in_array($paymentMethod, ['cod', 'bank_transfer'], true)) {
        $errors[] = 'Vui lòng chọn phương thức thanh toán hợp lệ.';
    }
    if ($items === []) {
        $errors[] = 'Giỏ hàng đang trống. Hãy thêm sản phẩm trước khi đặt hàng.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $lockedItems = [];
            $lockedProductsStatement = $pdo->prepare(
                'SELECT id, name, price, sale_price, stock, status
                 FROM products WHERE id = :id FOR UPDATE'
            );
            $stockUpdate = $pdo->prepare(
                'UPDATE products SET stock = stock - :decrement
                 WHERE id = :id AND status = :status AND stock >= :available'
            );
            $orderQuantities = $checkoutCart;
            ksort($orderQuantities, SORT_NUMERIC);
            $calculatedSubtotal = 0.0;

            foreach ($orderQuantities as $id => $quantity) {
                $productId = filter_var((string) $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $amount = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
                if ($productId === false || $amount === false) {
                    throw new RuntimeException('Giỏ hàng có sản phẩm hoặc số lượng không hợp lệ.');
                }

                $lockedProductsStatement->execute([':id' => $productId]);
                $product = $lockedProductsStatement->fetch();
                if ($product === false || $product['status'] !== 'active') {
                    throw new RuntimeException('Một sản phẩm không còn khả dụng. Vui lòng cập nhật giỏ hàng.');
                }
                if ($amount > (int) $product['stock']) {
                    throw new RuntimeException('Tồn kho sản phẩm "' . $product['name'] . '" vừa thay đổi. Vui lòng kiểm tra lại giỏ hàng.');
                }

                $unitPrice = $product['sale_price'] !== null && (float) $product['sale_price'] > 0
                    ? (float) $product['sale_price']
                    : (float) $product['price'];
                $lineTotal = $unitPrice * $amount;
                $stockUpdate->execute([
                    ':decrement' => $amount,
                    ':id' => $productId,
                    ':status' => 'active',
                    ':available' => $amount,
                ]);
                if ($stockUpdate->rowCount() !== 1) {
                    throw new RuntimeException('Không thể giữ tồn kho cho sản phẩm "' . $product['name'] . '". Vui lòng thử lại.');
                }
                $lockedItems[] = [
                    'id' => $productId,
                    'name' => $product['name'],
                    'price' => $unitPrice,
                    'quantity' => $amount,
                    'subtotal' => $lineTotal,
                ];
                $calculatedSubtotal += $lineTotal;
            }

            $orderCode = 'TZ' . date('ymd') . strtoupper(bin2hex(random_bytes(5)));
            $user = current_user();
            $orderInsert = $pdo->prepare(
                'INSERT INTO orders
                    (user_id, order_code, recipient_name, phone, address, note, subtotal, shipping_fee,
                     total_amount, payment_method, payment_status, order_status)
                 VALUES
                    (:user_id, :order_code, :recipient_name, :phone, :address, :note, :subtotal, :shipping_fee,
                     :total_amount, :payment_method, :payment_status, :order_status)'
            );
            $orderInsert->execute([
                ':user_id' => $user !== null ? (int) $user['id'] : null,
                ':order_code' => $orderCode,
                ':recipient_name' => $recipientName,
                ':phone' => $phone,
                ':address' => $address,
                ':note' => $note !== '' ? $note : null,
                ':subtotal' => $calculatedSubtotal,
                ':shipping_fee' => $shippingFee,
                ':total_amount' => $calculatedSubtotal + $shippingFee,
                ':payment_method' => $paymentMethod,
                ':payment_status' => 'unpaid',
                ':order_status' => 'pending',
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $itemInsert = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                 VALUES (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)'
            );
            foreach ($lockedItems as $item) {
                $itemInsert->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['id'],
                    ':product_name' => $item['name'],
                    ':price' => $item['price'],
                    ':quantity' => $item['quantity'],
                    ':subtotal' => $item['subtotal'],
                ]);
            }

            $pdo->commit();
            $remainingCart = $cart;
            foreach ($orderQuantities as $id => $quantity) {
                if (isset($remainingCart[$id])) {
                    $remainingQuantity = (int) $remainingCart[$id] - (int) $quantity;
                    if ($remainingQuantity > 0) {
                        $remainingCart[$id] = $remainingQuantity;
                    } else {
                        unset($remainingCart[$id]);
                    }
                }
            }
            $_SESSION['cart'] = $remainingCart;
            unset($_SESSION['checkout_items']);
            $_SESSION['last_order_code'] = $orderCode;
            redirect(BASE_URL . '/order-success.php');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('TechZone order transaction failed: ' . $exception->getMessage());
            $databaseError = true;
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading"><div><h1>Thông tin đặt hàng</h1><p>Đơn hàng sẽ được xác nhận sau khi cửa hàng kiểm tra.</p></div></div>
        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">Chưa thể xử lý đơn hàng do lỗi cơ sở dữ liệu. Không có thanh toán trực tuyến nào được thực hiện. Vui lòng thử lại sau.</div>
        <?php elseif ($items === []): ?>
            <div class="empty-state"><h2 class="h5">Không có sản phẩm để đặt hàng</h2><p>Hãy thêm sản phẩm vào giỏ hàng trước.</p><a class="btn btn-techzone" href="<?= e(BASE_URL) ?>/products.php">Tiếp tục mua sắm</a></div>
        <?php else: ?>
            <?php if ($errors !== []): ?><div class="alert alert-warning" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <div class="checkout-layout">
                <form method="post" action="<?= e(BASE_URL) ?>/checkout.php" class="checkout-form">
                    <?= csrf_field() ?>
                    <section class="checkout-panel">
                        <h2>Thông tin người nhận</h2>
                        <div class="form-grid">
                            <div><label class="form-label" for="recipient-name">Họ tên người nhận</label><input class="form-control" id="recipient-name" name="recipient_name" maxlength="150" autocomplete="name" value="<?= e($recipientName) ?>" required></div>
                            <div><label class="form-label" for="recipient-phone">Số điện thoại</label><input class="form-control" id="recipient-phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e($phone) ?>" required></div>
                            <div class="form-span"><label class="form-label" for="shipping-address">Địa chỉ giao hàng</label><textarea class="form-control" id="shipping-address" name="address" rows="3" maxlength="500" autocomplete="street-address" required><?= e($address) ?></textarea></div>
                            <div class="form-span"><label class="form-label" for="order-note">Ghi chú (không bắt buộc)</label><textarea class="form-control" id="order-note" name="note" rows="2" maxlength="2000"><?= e($note) ?></textarea></div>
                        </div>
                    </section>
                    <section class="checkout-panel">
                        <h2>Phương thức thanh toán</h2>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
                            <span><strong>Thanh toán khi nhận hàng (COD)</strong><small>Thanh toán trực tiếp cho đơn vị giao hàng khi nhận sản phẩm.</small></span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'checked' : '' ?>>
                            <span><strong>Chuyển khoản thủ công</strong><small>Đơn hàng ở trạng thái chưa thanh toán. Nhân viên sẽ liên hệ hướng dẫn chuyển khoản.</small></span>
                        </label>
                    </section>
                    <button class="btn btn-techzone btn-lg w-100" type="submit">Xác nhận đặt hàng</button>
                    <p class="checkout-disclaimer">Nút này tạo đơn hàng và giữ tồn kho; website chưa tích hợp cổng thanh toán trực tuyến.</p>
                </form>
                <aside class="cart-summary checkout-summary">
                    <h2>Đơn hàng của bạn</h2>
                    <?php foreach ($items as $item): ?>
                        <div class="checkout-product">
                            <span><?= e($item['name']) ?> <b>× <?= (int) $item['quantity'] ?></b></span>
                            <strong><?= e(format_vnd($item['line_total'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-line"><span>Tạm tính</span><strong><?= e(format_vnd($subtotal)) ?></strong></div>
                    <div class="summary-line"><span>Phí vận chuyển</span><span>0 ₫*</span></div>
                    <div class="summary-total"><span>Tổng cộng</span><strong><?= e(format_vnd($subtotal + $shippingFee)) ?></strong></div>
                    <p class="cart-note">* Phí vận chuyển hiện được đặt là 0 ₫; cửa hàng có thể xác nhận lại khi liên hệ.</p>
                    <a class="text-link" href="<?= e(BASE_URL) ?>/cart.php">← Quay lại giỏ hàng</a>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
