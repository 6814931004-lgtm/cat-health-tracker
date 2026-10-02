<?php
// ลืมรหัสผ่าน: ยืนยันตัวตนด้วย "ชื่อผู้ใช้ + อีเมล" แล้วตั้งรหัสใหม่
// หมายเหตุ: เป็นวิธีแบบง่ายสำหรับโปรเจกต์เรียน ระบบจริงควรส่งลิงก์รีเซ็ตทางอีเมล
require 'config.php';

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? AND email = ?');
    $stmt->execute([$username, $email]);
    $user_id = $stmt->fetchColumn();

    if (!$user_id) $errors[] = 'ไม่พบบัญชีที่ชื่อผู้ใช้และอีเมลตรงกัน';
    if (strlen($password) < 4) $errors[] = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 4 ตัว';
    if ($password !== $confirm) $errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user_id]);
        flash('ตั้งรหัสผ่านใหม่แล้ว เข้าสู่ระบบด้วยรหัสใหม่ได้เลย');
        header('Location: login.php');
        exit;
    }
}

$page_title = 'ลืมรหัสผ่าน';
require 'includes/header.php';
?>
<section class="auth-card">
  <img class="auth-logo" src="assets/logo.svg" alt="" width="56" height="56">
  <h2>ตั้งรหัสผ่านใหม่</h2>
  <p class="auth-sub">กรอกชื่อผู้ใช้และอีเมลที่ใช้สมัคร</p>
  <?php if ($errors): ?>
    <ul class="form-error" role="alert"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>ชื่อผู้ใช้
      <input type="text" name="username" value="<?= e($username) ?>" required>
    </label>
    <label>อีเมล
      <input type="email" name="email" value="<?= e($email) ?>" required>
    </label>
    <label>รหัสผ่านใหม่
      <input type="password" name="password" required autocomplete="new-password">
    </label>
    <label>ยืนยันรหัสผ่านใหม่
      <input type="password" name="confirm" required autocomplete="new-password">
    </label>
    <button type="submit" class="btn btn-primary btn-block">บันทึกรหัสผ่านใหม่</button>
  </form>

  <p class="auth-switch"><a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a></p>
</section>
<?php require 'includes/footer.php'; ?>
