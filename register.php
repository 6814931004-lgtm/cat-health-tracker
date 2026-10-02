<?php
require 'config.php';

$errors = [];
$data = ['full_name' => '', 'username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($data as $k => $v) {
        $data[$k] = trim($_POST[$k] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($data['full_name'] === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $data['username'])) {
        $errors[] = 'ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษ ตัวเลข หรือ _ ยาว 3–50 ตัว';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    if (strlen($password) < 4) $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 4 ตัว';
    if ($password !== $confirm) $errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['username'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['full_name']]);
        flash('สมัครสมาชิกสำเร็จ เข้าสู่ระบบได้เลย');
        header('Location: login.php');
        exit;
    }
}

$page_title = 'สมัครสมาชิก';
require 'includes/header.php';
?>
<section class="auth-card">
  <img class="auth-logo" src="assets/logo.svg" alt="" width="56" height="56">
  <h2>สมัครสมาชิก</h2>
  <?php if ($errors): ?>
    <ul class="form-error" role="alert"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>ชื่อ-นามสกุล
      <input type="text" name="full_name" value="<?= e($data['full_name']) ?>" required>
    </label>
    <label>ชื่อผู้ใช้ (ภาษาอังกฤษ)
      <input type="text" name="username" value="<?= e($data['username']) ?>" required autocomplete="username">
    </label>
    <label>อีเมล
      <input type="email" name="email" value="<?= e($data['email']) ?>" required>
    </label>
    <label>รหัสผ่าน
      <input type="password" name="password" required autocomplete="new-password">
    </label>
    <label>ยืนยันรหัสผ่าน
      <input type="password" name="confirm" required autocomplete="new-password">
    </label>
    <button type="submit" class="btn btn-primary btn-block">สมัครสมาชิก</button>
  </form>

  <p class="auth-switch">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
</section>
<?php require 'includes/footer.php'; ?>
