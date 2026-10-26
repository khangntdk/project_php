<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user() !== null) {
    redirect(BASE_URL . '/profile.php');
}

$pageTitle = 'Đăng nhập';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu hợp lệ.';
    } else {
        try {
            $statement = db()->prepare(
                'SELECT id, full_name, email, phone, password, role FROM users WHERE email = :email LIMIT 1'
            );
            $statement->execute([':email' => $email]);
            $user = $statement->fetch();

            if ($user === false || !password_verify($password, $user['password'])) {
                $error = 'Email hoặc mật khẩu không chính xác.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                ];
                unset($_SESSION['csrf_token']);
                set_flash('success', 'Đăng nhập thành công.');
                redirect(BASE_URL . '/profile.php');
            }
        } catch (PDOException $exception) {
            error_log('TechZone login query failed: ' . $exception->getMessage());
            $error = 'Chưa thể đăng nhập lúc này. Vui lòng thử lại sau.';
        } catch (RuntimeException $exception) {
            error_log('TechZone login database error: ' . $exception->getMessage());
            $error = 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình và thử lại.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container auth-container">
        <div class="auth-card">
            <div class="auth-heading"><h1>Đăng nhập</h1><p>Đăng nhập để quản lý tài khoản và đơn hàng của bạn.</p></div>
            <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="<?= e(BASE_URL) ?>/login.php" class="form-grid">
                <?= csrf_field() ?>
                <div><label class="form-label" for="login-email">Email</label><input class="form-control" id="login-email" type="email" name="email" maxlength="190" autocomplete="email" value="<?= e($email) ?>" required></div>
                <div><label class="form-label" for="login-password">Mật khẩu</label><input class="form-control" id="login-password" type="password" name="password" autocomplete="current-password" required></div>
                <button class="btn btn-techzone btn-lg w-100" type="submit">Đăng nhập</button>
            </form>
            <p class="auth-switch">Chưa có tài khoản? <a href="<?= e(BASE_URL) ?>/register.php">Đăng ký ngay</a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
