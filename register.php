<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user() !== null) {
    redirect(BASE_URL . '/profile.php');
}

$pageTitle = 'Tạo tài khoản';
$errors = [];
$fullName = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $passwordConfirmation = isset($_POST['password_confirmation']) && is_string($_POST['password_confirmation'])
        ? $_POST['password_confirmation']
        : '';

    if ($fullName === '' || strlen($fullName) > 150) {
        $errors[] = 'Họ tên không được để trống và tối đa 150 ký tự.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Vui lòng nhập địa chỉ email hợp lệ.';
    }
    if (!preg_match('/^[0-9+().\-\s]{8,30}$/', $phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại hợp lệ.';
    }
    if (strlen($password) < 8 || strlen($password) > 4096) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'INSERT INTO users (full_name, email, phone, password, role)
                 VALUES (:full_name, :email, :phone, :password, :role)'
            );
            $statement->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'customer',
            ]);

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) db()->lastInsertId(),
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'role' => 'customer',
            ];
            unset($_SESSION['csrf_token']);
            set_flash('success', 'Tạo tài khoản thành công. Chào mừng bạn đến với TechZone!');
            redirect(BASE_URL . '/profile.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Email này đã được sử dụng. Vui lòng đăng nhập hoặc dùng email khác.';
            } else {
                error_log('TechZone registration failed: ' . $exception->getMessage());
                $errors[] = 'Chưa thể tạo tài khoản lúc này. Vui lòng thử lại sau.';
            }
        } catch (RuntimeException $exception) {
            error_log('TechZone registration database error: ' . $exception->getMessage());
            $errors[] = 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình và thử lại.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container auth-container">
        <div class="auth-card">
            <div class="auth-heading"><h1>Tạo tài khoản TechZone</h1><p>Đăng ký để theo dõi đơn hàng và quản lý thông tin cá nhân.</p></div>
            <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="<?= e(BASE_URL) ?>/register.php" class="form-grid">
                <?= csrf_field() ?>
                <div><label class="form-label" for="full-name">Họ và tên</label><input class="form-control" id="full-name" name="full_name" maxlength="150" autocomplete="name" value="<?= e($fullName) ?>" required></div>
                <div><label class="form-label" for="register-email">Email</label><input class="form-control" id="register-email" type="email" name="email" maxlength="190" autocomplete="email" value="<?= e($email) ?>" required></div>
                <div><label class="form-label" for="register-phone">Số điện thoại</label><input class="form-control" id="register-phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e($phone) ?>" required></div>
                <div><label class="form-label" for="register-password">Mật khẩu</label><input class="form-control" id="register-password" type="password" name="password" minlength="8" autocomplete="new-password" required></div>
                <div><label class="form-label" for="password-confirmation">Nhập lại mật khẩu</label><input class="form-control" id="password-confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></div>
                <button class="btn btn-techzone btn-lg w-100" type="submit">Đăng ký</button>
            </form>
            <p class="auth-switch">Đã có tài khoản? <a href="<?= e(BASE_URL) ?>/login.php">Đăng nhập</a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
