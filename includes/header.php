<?php
// ใช้: $page_title = '...'; require 'includes/header.php';
$logged_in = !empty($_SESSION['user_id']);
$alert_count = $logged_in ? count_alerts($pdo) : 0;
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Cat Health Tracker') ?> | Cat Health Tracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;500&family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="icon" href="assets/logo.svg">
</head>
<body class="<?= $logged_in ? 'app' : 'auth' ?>">
<?php if ($logged_in): ?>
<header class="topbar">
  <a class="brand" href="index.php">
    <img src="assets/logo.svg" alt="" width="34" height="34">
    <span>Cat Health Tracker</span>
  </a>
  <a class="bell<?= $current === 'notifications.php' ? ' is-active' : '' ?>" href="notifications.php" aria-label="การแจ้งเตือน <?= $alert_count ?> รายการ">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3a1.5 1.5 0 0 0-3 0v1.16A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/></svg>
    <?php if ($alert_count > 0): ?><span class="badge"><?= $alert_count ?></span><?php endif; ?>
  </a>
  <details class="menu">
    <summary aria-label="เมนู">
      <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg>
    </summary>
    <nav>
      <p class="menu-user">สวัสดี, <?= e($_SESSION['full_name']) ?></p>
      <a href="index.php">แมวของฉัน</a>
      <a href="cat_form.php">เพิ่มบัญชีแมว</a>
      <a href="notifications.php">การแจ้งเตือน</a>
      <a href="report.php">ข้อมูลทั้งหมด / รายงาน</a>
      <a href="logout.php" class="menu-logout">ออกจากระบบ</a>
    </nav>
  </details>
</header>
<?php endif; ?>
<main class="container">
<?php if ($f = flash()): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endif; ?>
