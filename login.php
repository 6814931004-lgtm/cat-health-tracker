<?php
require 'config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        header('Location: index.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

$page_title = 'เข้าสู่ระบบ';
require 'includes/header.php';
?>
<section class="auth-card">
  <img class="auth-logo" src="assets/logo.svg" alt="" width="72" height="72">
  <h1>Cat Health Tracker</h1>
  <p class="auth-sub">ระบบบันทึกสุขภาพแมว</p>

  <h2>เข้าสู่ระบบ</h2>
  <?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>ชื่อผู้ใช้หรืออีเมล
      <input type="text" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
    </label>
    <label>รหัสผ่าน
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <a class="small-link" href="forgot_password.php">ลืมรหัสผ่าน?</a>
    <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
  </form>

  <p class="auth-switch">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
</section>
<?php require 'includes/footer.php'; ?>
