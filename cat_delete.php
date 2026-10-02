<?php
require 'config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
check_csrf();

$cat = get_own_cat($pdo, $_POST['cat_id'] ?? 0);
if ($cat) {
    // วัคซีนและบันทึกสุขภาพจะถูกลบตามอัตโนมัติ (ON DELETE CASCADE)
    $pdo->prepare('DELETE FROM cats WHERE cat_id = ? AND user_id = ?')
        ->execute([$cat['cat_id'], $_SESSION['user_id']]);

    if ($cat['photo'] && file_exists(__DIR__ . '/uploads/' . $cat['photo'])) {
        unlink(__DIR__ . '/uploads/' . $cat['photo']);
    }
    flash('ลบข้อมูล' . $cat['cat_name'] . 'แล้ว');
} else {
    flash('ไม่พบข้อมูลแมวตัวนี้', 'error');
}
header('Location: index.php');
exit;
