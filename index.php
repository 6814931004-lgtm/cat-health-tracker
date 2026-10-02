<?php
require 'config.php';
require_login();

// ดึงแมวทั้งหมดของผู้ใช้ พร้อมน้ำหนักล่าสุด และนัดหมอครั้งถัดไป
$sql = "SELECT c.*,
          (SELECT weight_kg FROM health_records h
            WHERE h.cat_id = c.cat_id AND h.weight_kg IS NOT NULL
            ORDER BY h.record_date DESC, h.record_id DESC LIMIT 1) AS last_weight,
          (SELECT MIN(next_appointment_date) FROM health_records h
            WHERE h.cat_id = c.cat_id AND DATE(h.next_appointment_date) >= CURDATE()) AS next_appt
        FROM cats c
        WHERE c.user_id = ?
        ORDER BY c.cat_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$cats = $stmt->fetchAll();

$page_title = 'แมวของฉัน';
require 'includes/header.php';
?>
<div class="page-head">
  <h1>แมวของฉัน</h1>
  <a class="btn btn-primary" href="cat_form.php">+ เพิ่มแมว</a>
</div>

<?php if ($alert_count > 0): ?>
  <a class="alert-strip" href="notifications.php">
    มีเรื่องต้องทำใน 7 วันนี้ <?= $alert_count ?> รายการ — ดูการแจ้งเตือน
  </a>
<?php endif; ?>

<?php if (!$cats): ?>
  <div class="empty">
    <img src="assets/cat-default.svg" alt="" width="96" height="96">
    <p>ยังไม่มีแมวในระบบ เพิ่มน้องแมวตัวแรกเพื่อเริ่มบันทึกวัคซีน น้ำหนัก และนัดหมอ</p>
    <a class="btn btn-primary" href="cat_form.php">เพิ่มแมวตัวแรก</a>
  </div>
<?php else: ?>
  <ul class="cat-list">
    <?php foreach ($cats as $c): ?>
      <li class="cat-row">
        <a class="cat-link" href="cat_detail.php?id=<?= $c['cat_id'] ?>">
          <img class="avatar" src="<?= e(cat_photo($c['photo'])) ?>" alt="รูปของ<?= e($c['cat_name']) ?>" width="72" height="72">
          <span class="cat-info">
            <strong class="cat-name"><?= e($c['cat_name']) ?></strong>
            <span class="cat-meta">
              <?= e($c['breed'] ?: 'ไม่ระบุสายพันธุ์') ?>,
              <?= $c['gender'] === 'ผู้' ? 'เพศผู้' : 'เพศเมีย' ?>,
              อายุ <?= e(cat_age($c['birth_date'])) ?>
            </span>
            <span class="cat-stats">
              <span>น้ำหนัก <?= $c['last_weight'] !== null ? e($c['last_weight']) . ' กก.' : '-' ?></span>
              <?php if ($c['next_appt']): ?>
                <span class="chip chip-appt">นัดหมอ <?= e(thai_date($c['next_appt'])) ?></span>
              <?php endif; ?>
            </span>
          </span>
        </a>
        <div class="row-actions">
          <a class="btn btn-ghost" href="cat_form.php?id=<?= $c['cat_id'] ?>">แก้ไข</a>
          <form method="post" action="cat_delete.php"
                onsubmit="return confirm(<?= e(json_encode('ลบข้อมูลของ ' . $c['cat_name'] . ' พร้อมประวัติทั้งหมด? การลบย้อนกลับไม่ได้', JSON_UNESCAPED_UNICODE)) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="cat_id" value="<?= $c['cat_id'] ?>">
            <button type="submit" class="btn btn-ghost btn-danger-text">ลบ</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
