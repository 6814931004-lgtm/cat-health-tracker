<?php
require 'config.php';
require_login();

$uid = $_SESSION['user_id'];

// นัดหมอที่ยังไม่ถึง (ภายใน 30 วัน)
$stmt = $pdo->prepare("SELECT h.*, c.cat_name, c.photo
                       FROM health_records h JOIN cats c ON c.cat_id = h.cat_id
                       WHERE c.user_id = ? AND h.next_appointment_date IS NOT NULL
                         AND DATE(h.next_appointment_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                       ORDER BY h.next_appointment_date");
$stmt->execute([$uid]);
$appts = $stmt->fetchAll();

// วัคซีนที่ใกล้ถึงกำหนด (30 วัน) หรือเลยกำหนดแล้ว — เฉพาะเข็มล่าสุดของแต่ละชนิด
$stmt = $pdo->prepare("SELECT v.*, c.cat_name, c.photo, DATEDIFF(v.next_due_date, CURDATE()) AS days_left
                       FROM vaccinations v JOIN cats c ON c.cat_id = v.cat_id
                       WHERE c.user_id = ? AND v.next_due_date IS NOT NULL
                         AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                         AND NOT EXISTS (SELECT 1 FROM vaccinations v2
                                         WHERE v2.cat_id = v.cat_id AND v2.vaccine_name = v.vaccine_name
                                           AND v2.vaccine_date > v.vaccine_date)
                       ORDER BY v.next_due_date");
$stmt->execute([$uid]);
$vaccines = $stmt->fetchAll();

function days_text($date) {
    $d = (int)floor((strtotime(substr($date, 0, 10)) - strtotime(date('Y-m-d'))) / 86400);
    if ($d < 0)  return 'เลยกำหนดมา ' . abs($d) . ' วัน';
    if ($d === 0) return 'วันนี้';
    if ($d === 1) return 'พรุ่งนี้';
    return 'อีก ' . $d . ' วัน';
}

$page_title = 'การแจ้งเตือน';
require 'includes/header.php';
?>
<div class="page-head">
  <h1>การแจ้งเตือน</h1>
</div>

<section class="notice-group">
  <h2>นัดหมอใน 30 วันข้างหน้า</h2>
  <?php if (!$appts): ?>
    <p class="muted">ไม่มีนัดหมอในช่วงนี้</p>
  <?php endif; ?>
  <?php foreach ($appts as $a): $urgent = substr($a['next_appointment_date'], 0, 10) <= date('Y-m-d', strtotime('+1 day')); ?>
    <article class="slip<?= $urgent ? ' slip-urgent' : '' ?>">
      <img class="avatar" src="<?= e(cat_photo($a['photo'])) ?>" alt="" width="64" height="64">
      <div>
        <p class="slip-title"><?= e(days_text($a['next_appointment_date'])) ?></p>
        <p class="slip-msg">
          น้อง<?= e($a['cat_name']) ?> มีนัดกับคุณหมอวันที่ <?= e(thai_date($a['next_appointment_date'], true)) ?>
          อย่าลืมพาน้องแมวมาตามนัดนะ
          <?php if ($a['vet_clinic']): ?>สถานที่: <?= e($a['vet_clinic']) ?><?php endif; ?>
        </p>
        <?php if ($a['appointment_reason']): ?>
          <p class="muted">นัดต่อจากครั้งก่อน: <?= e($a['appointment_reason']) ?></p>
        <?php endif; ?>
        <a href="cat_detail.php?id=<?= $a['cat_id'] ?>#records">ดูประวัติของน้อง<?= e($a['cat_name']) ?></a>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<section class="notice-group">
  <h2>วัคซีนที่ต้องฉีด</h2>
  <?php if (!$vaccines): ?>
    <p class="muted">วัคซีนของทุกตัวยังไม่ถึงกำหนด</p>
  <?php endif; ?>
  <?php foreach ($vaccines as $v): $late = (int)$v['days_left'] < 0; ?>
    <article class="slip<?= $late ? ' slip-late' : '' ?>">
      <img class="avatar" src="<?= e(cat_photo($v['photo'])) ?>" alt="" width="64" height="64">
      <div>
        <p class="slip-title"><?= e(days_text($v['next_due_date'])) ?></p>
        <p class="slip-msg">
          น้อง<?= e($v['cat_name']) ?> ถึงกำหนดฉีด <?= e($v['vaccine_name']) ?>
          วันที่ <?= e(thai_date($v['next_due_date'])) ?>
          <?php if ($v['vet_clinic']): ?>(เข็มก่อนฉีดที่ <?= e($v['vet_clinic']) ?>)<?php endif; ?>
        </p>
        <a href="cat_detail.php?id=<?= $v['cat_id'] ?>#vaccines">บันทึกเมื่อฉีดแล้ว</a>
      </div>
    </article>
  <?php endforeach; ?>
</section>
<?php require 'includes/footer.php'; ?>
