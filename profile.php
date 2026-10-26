<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Thông tin tài khoản';
$user = current_user();
$errors = [];
$fullName = (string) ($user['full_name'] ?? '');
$phone = (string) ($user['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'profile';

    if ($action === 'profile') {
        $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';

        if ($fullName === '' || strlen($fullName) > 150) {
            $errors[] = 'Họ tên không được để trống và tối đa 150 ký tự.';
        }
        if (!preg_match('/^[0-9+().\-\s]{8,30}$/', $phone)) {
            $errors[] = 'Vui lòng nhập số điện thoại hợp lệ.';
        }

        if ($errors === []) {
            try {
                $statement = db()->prepare('UPDATE users SET full_name = :full_name, phone = :phone WHERE id = :id');
                $statement->execute([
                    ':full_name' => $fullName,
                    ':phone' => $phone,
                    ':id' => (int) $user['id'],
                ]);
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['phone'] = $phone;
                set_flash('success', 'Thông tin cá nhân đã được cập nhật.');
                redirect(BASE_URL . '/profile.php');
            } catch (PDOException | RuntimeException $exception) {
                error_log('TechZone profile update failed: ' . $exception->getMessage());
                $errors[] = 'Chưa thể cập nhật thông tin lúc này. Vui lòng thử lại sau.';
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmation = isset($_POST['new_password_confirmation']) && is_string($_POST['new_password_confirmation'])
            ? $_POST['new_password_confirmation']
            : '';

        if (strlen($newPassword) < 8 || strlen($newPassword) > 4096) {
            $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        }
        if ($newPassword !== $confirmation) {
            $errors[] = 'Mật khẩu xác nhận không khớp.';
        }

        if ($errors === []) {
            try {
                $statement = db()->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
                $statement->execute([':id' => (int) $user['id']]);
                $passwordHash = $statement->fetchColumn();

                if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
                    $errors[] = 'Mật khẩu hiện tại không chính xác.';
                } else {
                    $update = db()->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $update->execute([
                        ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        ':id' => (int) $user['id'],
                    ]);
                    set_flash('success', 'Mật khẩu đã được cập nhật.');
                    redirect(BASE_URL . '/profile.php');
                }
            } catch (PDOException | RuntimeException $exception) {
                error_log('TechZone password update failed: ' . $exception->getMessage());
                $errors[] = 'Chưa thể cập nhật mật khẩu lúc này. Vui lòng thử lại sau.';
            }
        }
    } else {
        http_response_code(400);
        $errors[] = 'Thao tác không hợp lệ.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container account-layout">
        <aside class="account-sidebar">
            <h1>Tài khoản</h1>
            <p><?= e($user['email'] ?? '') ?></p>
            <a class="account-nav-link active" href="<?= e(BASE_URL) ?>/profile.php">Thông tin cá nhân</a>
            <a class="account-nav-link" href="<?= e(BASE_URL) ?>/orders-history.php">Lịch sử mua hàng</a>
        </aside>
        <div class="account-content">
            <h2>Thông tin cá nhân</h2>
            <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" class="account-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <div><label class="form-label" for="profile-name">Họ và tên</label><input class="form-control" id="profile-name" name="full_name" maxlength="150" autocomplete="name" value="<?= e($fullName) ?>" required></div>
                <div><label class="form-label" for="profile-email">Email</label><input class="form-control" id="profile-email" type="email" value="<?= e($user['email'] ?? '') ?>" readonly></div>
                <div><label class="form-label" for="profile-phone">Số điện thoại</label><input class="form-control" id="profile-phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e($phone) ?>" required></div>
                <button class="btn btn-techzone" type="submit">Lưu thông tin</button>
            </form>
            <hr class="my-4">
            <h2>Đổi mật khẩu</h2>
            <form method="post" class="account-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div><label class="form-label" for="current-password">Mật khẩu hiện tại</label><input class="form-control" id="current-password" type="password" name="current_password" autocomplete="current-password" required></div>
                <div><label class="form-label" for="new-password">Mật khẩu mới</label><input class="form-control" id="new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required></div>
                <div><label class="form-label" for="new-password-confirmation">Nhập lại mật khẩu mới</label><input class="form-control" id="new-password-confirmation" type="password" name="new_password_confirmation" minlength="8" autocomplete="new-password" required></div>
                <button class="btn btn-outline-primary" type="submit">Đổi mật khẩu</button>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
