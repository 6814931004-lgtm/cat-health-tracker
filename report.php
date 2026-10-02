<?php
// ข้อมูลทั้งหมด / รายงานสรุปของแมวทุกตัว
require 'config.php';
require_login();

$uid = $_SESSION['user_id'];

$sql = "SELECT c.cat_id, c.cat_name, c.breed, c.gender, c.birth_date,
          (SELECT COUNT(*) FROM vaccinations v WHERE v.cat_id = c.cat_id) AS vaccine_count,
          (SELECT MAX(vaccine_date) FROM vaccinations v WHERE v.cat_id = c.cat_id) AS last_vaccine,
          (SELECT COUNT(*) FROM health_records h WHERE h.cat_id = c.cat_id AND h.appointment_reason IS NOT NULL) AS visit_count,
          (SELECT weight_kg FROM health_records h WHERE h.cat_id = c.cat_id AND h.weight_kg IS NOT NULL
             ORDER BY record_date DESC, record_id DESC LIMIT 1) AS last_weight,
          (SELECT weight_kg FROM health_records h WHERE h.cat_id = c.cat_id AND h.weight_kg IS NOT NULL
             ORDER BY record_date DESC, record_id DESC LIMIT 1 OFFSET 1) AS prev_weight,
          (SELECT MIN(next_appointment_date) FROM health_records h
             WHERE h.cat_id = c.cat_id AND DATE(h.next_appointment_date) >= CURDATE()) AS next_appt
        FROM cats c WHERE c.user_id = ? ORDER BY c.cat_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();

$totals = [
    'cats'     => count($rows),
    'vaccines' => array_sum(array_column($rows, 'vaccine_count')),
    'visits'   => array_sum(array_column($rows, 'visit_count')),
    'appts'    => count(array_filter($rows, fn($r) => $r['next_appt'])),
];

$page_title = 'ข้อมูลทั้งหมด';
require 'includes/header.php';
?>
<div class="page-head">
  <h1>ข้อมูลทั้งหมด</h1>
  <button class="btn btn-ghost no-print" type="button" onclick="window.print()">พิมพ์รายงาน</button>
</div>

<p class="summary-line">
  แมว <strong><?= $totals['cats'] ?></strong> ตัว,
  ฉีดวัคซีนแล้ว <strong><?= $totals['vaccines'] ?></strong> เข็ม,
  พบหมอ <strong><?= $totals['visits'] ?></strong> ครั้ง,
  มีนัดที่รออยู่ <strong><?= $totals['appts'] ?></strong> ตัว
</p>

<?php if (!$rows): ?>
  <p class="muted">ยังไม่มีข้อมูล <a href="cat_form.php">เพิ่มแมวตัวแรก</a></p>
<?php else: ?>
  <div class="table-wrap panel">
    <table>
      <thead>
        <tr>
          <th>ชื่อ</th><th>สายพันธุ์</th><th>เพศ</th><th>อายุ</th>
          <th>น้ำหนักล่าสุด</th><th>วัคซีน (เข็ม)</th><th>ฉีดล่าสุด</th><th>นัดถัดไป</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $trend = '';
        if ($r['last_weight'] !== null && $r['prev_weight'] !== null) {
            $diff = round($r['last_weight'] - $r['prev_weight'], 2);
            if ($diff > 0) $trend = '<span class="trend up">+' . $diff . '</span>';
            elseif ($diff < 0) $trend = '<span class="trend down">' . $diff . '</span>';
        }
      ?>
        <tr>
          <td><a href="cat_detail.php?id=<?= $r['cat_id'] ?>"><?= e($r['cat_name']) ?></a></td>
          <td><?= e($r['breed'] ?: '-') ?></td>
          <td><?= e($r['gender']) ?></td>
          <td><?= e(cat_age($r['birth_date'])) ?></td>
          <td><?= $r['last_weight'] !== null ? e($r['last_weight']) . ' กก. ' . $trend : '-' ?></td>
          <td><?= (int)$r['vaccine_count'] ?></td>
          <td><?= e(thai_date($r['last_vaccine'])) ?></td>
          <td><?= e(thai_date($r['next_appt'], true)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
