<?php
require 'config.php';
require_login();

$cat = get_own_cat($pdo, $_GET['id'] ?? 0);
if (!$cat) {
    flash('ไม่พบข้อมูลแมวตัวนี้', 'error');
    header('Location: index.php');
    exit;
}
$cat_id = $cat['cat_id'];
$self = 'cat_detail.php?id=' . $cat_id;
$errors = [];

// =========================================================
// จัดการฟอร์ม (เพิ่ม/ลบ วัคซีน และ บันทึกสุขภาพ)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_vaccine') {
        $name  = trim($_POST['vaccine_name'] ?? '');
        $date  = $_POST['vaccine_date'] ?? '';
        $next  = $_POST['next_due_date'] ?? '';
        $place = trim($_POST['vet_clinic'] ?? '');

        if ($name === '') $errors[] = 'กรุณากรอกชื่อวัคซีน';
        if ($date === '') $errors[] = 'กรุณาเลือกวันที่ฉีดวัคซีน';
        if ($next !== '' && $date !== '' && $next <= $date) $errors[] = 'วันฉีดครั้งถัดไปต้องอยู่หลังวันที่ฉีด';

        if (!$errors) {
            $pdo->prepare('INSERT INTO vaccinations (cat_id, vaccine_name, vaccine_date, next_due_date, vet_clinic)
                           VALUES (?, ?, ?, ?, ?)')
                ->execute([$cat_id, $name, $date, $next ?: null, $place ?: null]);
            flash('บันทึกวัคซีน ' . $name . ' แล้ว');
            header('Location: ' . $self . '#vaccines');
            exit;
        }
    }

    if ($action === 'add_record') {
        $date   = $_POST['record_date'] ?? '';
        $weight = $_POST['weight_kg'] ?? '';
        $reason = trim($_POST['appointment_reason'] ?? '');
        $diag   = trim($_POST['diagnosis'] ?? '');
        $next   = $_POST['next_appointment_date'] ?? '';
        $place  = trim($_POST['vet_clinic'] ?? '');

        if ($date === '') $errors[] = 'กรุณาเลือกวันที่บันทึก';
        if ($weight !== '' && (!is_numeric($weight) || $weight <= 0 || $weight > 30)) {
            $errors[] = 'น้ำหนักต้องเป็นตัวเลขระหว่าง 0.1–30 กก.';
        }
        if ($weight === '' && $reason === '' && $diag === '' && $next === '') {
            $errors[] = 'กรอกอย่างน้อยหนึ่งอย่าง: น้ำหนัก เหตุผลที่พบหมอ ผลตรวจ หรือวันนัดครั้งถัดไป';
        }

        if (!$errors) {
            $pdo->prepare('INSERT INTO health_records
                             (cat_id, record_date, appointment_reason, diagnosis, next_appointment_date, vet_clinic, weight_kg)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $cat_id, $date, $reason ?: null, $diag ?: null,
                    $next ? str_replace('T', ' ', $next) : null, $place ?: null,
                    $weight !== '' ? $weight : null,
                ]);
            flash('บันทึกข้อมูลสุขภาพแล้ว');
            header('Location: ' . $self . '#records');
            exit;
        }
    }

    if ($action === 'delete_vaccine') {
        $pdo->prepare('DELETE FROM vaccinations WHERE vaccine_id = ? AND cat_id = ?')
            ->execute([(int)$_POST['vaccine_id'], $cat_id]);
        flash('ลบรายการวัคซีนแล้ว');
        header('Location: ' . $self . '#vaccines');
        exit;
    }

    if ($action === 'delete_record') {
        $pdo->prepare('DELETE FROM health_records WHERE record_id = ? AND cat_id = ?')
            ->execute([(int)$_POST['record_id'], $cat_id]);
        flash('ลบบันทึกสุขภาพแล้ว');
        header('Location: ' . $self . '#records');
        exit;
    }
}

// =========================================================
// ดึงข้อมูลมาแสดง
// =========================================================
$stmt = $pdo->prepare('SELECT * FROM vaccinations WHERE cat_id = ? ORDER BY vaccine_date DESC');
$stmt->execute([$cat_id]);
$vaccines = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM health_records WHERE cat_id = ? ORDER BY record_date DESC, record_id DESC');
$stmt->execute([$cat_id]);
$records = $stmt->fetchAll();

// ข้อมูลน้ำหนักสำหรับกราฟ (เรียงจากเก่าไปใหม่)
$weights = array_reverse(array_values(array_filter($records, fn($r) => $r['weight_kg'] !== null)));

// นัดหมอครั้งถัดไปที่ยังไม่ถึง
$next_appt = null;
foreach ($records as $r) {
    if ($r['next_appointment_date'] && substr($r['next_appointment_date'], 0, 10) >= date('Y-m-d')) {
        if (!$next_appt || $r['next_appointment_date'] < $next_appt['next_appointment_date']) {
            $next_appt = $r;
        }
    }
}

// สถานะวัคซีน: เลยกำหนด / ใกล้ถึง (30 วัน) / ปกติ
// นับเฉพาะเข็มล่าสุดของวัคซีนแต่ละชนิด
$latest_by_name = [];
foreach ($vaccines as $v) {
    if (!isset($latest_by_name[$v['vaccine_name']])) $latest_by_name[$v['vaccine_name']] = $v['vaccine_id'];
}
function vaccine_status($v, $latest_by_name) {
    if ($latest_by_name[$v['vaccine_name']] !== $v['vaccine_id']) return ['done', 'ฉีดซ้ำแล้ว'];
    if (!$v['next_due_date']) return ['', ''];
    $days = (int)floor((strtotime($v['next_due_date']) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)   return ['late', 'เลยกำหนด ' . abs($days) . ' วัน'];
    if ($days <= 30) return ['soon', 'อีก ' . $days . ' วัน'];
    return ['ok', 'ปกติ'];
}

// สร้างกราฟน้ำหนักเป็น SVG (ไม่ต้องใช้ไลบรารีภายนอก)
function weight_chart($weights) {
    $n = count($weights);
    if ($n < 2) return '';
    $W = 720; $H = 220; $padL = 44; $padR = 16; $padT = 16; $padB = 36;
    $vals = array_map(fn($w) => (float)$w['weight_kg'], $weights);
    $min = floor(min($vals) * 2) / 2 - 0.5;
    $max = ceil(max($vals) * 2) / 2 + 0.5;
    $x = fn($i) => $padL + ($W - $padL - $padR) * ($n === 1 ? 0.5 : $i / ($n - 1));
    $y = fn($v) => $padT + ($H - $padT - $padB) * (1 - ($v - $min) / ($max - $min));

    $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="กราฟน้ำหนักแมว">';
    // เส้นตาราง
    for ($v = $min; $v <= $max + 0.001; $v += 0.5) {
        $yy = round($y($v), 1);
        $svg .= '<line x1="' . $padL . '" x2="' . ($W - $padR) . '" y1="' . $yy . '" y2="' . $yy . '" class="grid"/>';
        $svg .= '<text x="' . ($padL - 8) . '" y="' . ($yy + 4) . '" text-anchor="end" class="axis">' . number_format($v, 1) . '</text>';
    }
    // เส้นกราฟ
    $points = [];
    foreach ($vals as $i => $v) $points[] = round($x($i), 1) . ',' . round($y($v), 1);
    $svg .= '<polyline points="' . implode(' ', $points) . '" class="line"/>';
    // จุด + วันที่
    foreach ($weights as $i => $w) {
        $cx = round($x($i), 1); $cy = round($y((float)$w['weight_kg']), 1);
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="5" class="dot"><title>'
              . e(thai_date($w['record_date'])) . ': ' . e($w['weight_kg']) . ' กก.</title></circle>';
        if ($n <= 8 || $i === 0 || $i === $n - 1) {
            $anchor = $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle');
            $svg .= '<text x="' . $cx . '" y="' . ($H - 12) . '" text-anchor="' . $anchor . '" class="axis">'
                  . e(thai_date($w['record_date'])) . '</text>';
        }
    }
    return $svg . '</svg>';
}

$page_title = $cat['cat_name'];
require 'includes/header.php';
?>
<a class="back" href="index.php">‹ แมวของฉัน</a>

<?php if ($errors): ?>
  <ul class="form-error" role="alert"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<!-- ข้อมูลแมว -->
<section class="profile">
  <img class="avatar avatar-xl" src="<?= e(cat_photo($cat['photo'])) ?>" alt="รูปของ<?= e($cat['cat_name']) ?>" width="140" height="140">
  <div class="profile-body">
    <h1><?= e($cat['cat_name']) ?></h1>
    <dl class="facts">
      <div><dt>สายพันธุ์</dt><dd><?= e($cat['breed'] ?: '-') ?></dd></div>
      <div><dt>เพศ</dt><dd><?= e($cat['gender']) ?></dd></div>
      <div><dt>วันเกิด</dt><dd><?= e(thai_date($cat['birth_date'])) ?></dd></div>
      <div><dt>อายุ</dt><dd><?= e(cat_age($cat['birth_date'])) ?></dd></div>
      <div><dt>น้ำหนักล่าสุด</dt><dd><?= $weights ? e(end($weights)['weight_kg']) . ' กก.' : '-' ?></dd></div>
      <div><dt>เจ้าของ</dt><dd><?= e($cat['owner_name'] ?: '-') ?></dd></div>
    </dl>
    <a class="btn btn-ghost" href="cat_form.php?id=<?= $cat_id ?>">แก้ไขข้อมูลแมว</a>
  </div>
</section>

<?php if ($next_appt): ?>
  <aside class="slip">
    <div>
      <p class="slip-title">นัดหมอครั้งถัดไป</p>
      <p class="slip-when"><?= e(thai_date($next_appt['next_appointment_date'], true)) ?></p>
      <?php if ($next_appt['vet_clinic']): ?><p>ที่ <?= e($next_appt['vet_clinic']) ?></p><?php endif; ?>
    </div>
  </aside>
<?php endif; ?>

<!-- วัคซีน -->
<section class="panel" id="vaccines">
  <div class="panel-head">
    <h2>ประวัติวัคซีน</h2>
  </div>
  <?php if (!$vaccines): ?>
    <p class="muted">ยังไม่มีประวัติวัคซีน เพิ่มเข็มแรกจากฟอร์มด้านล่าง</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>วัคซีน</th><th>วันที่ฉีด</th><th>ครั้งถัดไป</th><th>สถานที่</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($vaccines as $v): [$cls, $label] = vaccine_status($v, $latest_by_name); ?>
          <tr>
            <td><?= e($v['vaccine_name']) ?></td>
            <td><?= e(thai_date($v['vaccine_date'])) ?></td>
            <td>
              <?= e(thai_date($v['next_due_date'])) ?>
              <?php if ($label): ?><span class="status status-<?= $cls ?>"><?= e($label) ?></span><?php endif; ?>
            </td>
            <td><?= e($v['vet_clinic'] ?: '-') ?></td>
            <td>
              <form method="post" onsubmit="return confirm('ลบรายการวัคซีนนี้?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_vaccine">
                <input type="hidden" name="vaccine_id" value="<?= $v['vaccine_id'] ?>">
                <button class="link-btn" type="submit">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <details class="add-form" <?= ($_POST['action'] ?? '') === 'add_vaccine' ? 'open' : '' ?>>
    <summary class="btn btn-ghost">+ เพิ่มวัคซีน</summary>
    <form method="post" class="form form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_vaccine">
      <label>ชื่อวัคซีน *
        <input type="text" name="vaccine_name" required list="vaccine-list" value="<?= e($_POST['vaccine_name'] ?? '') ?>">
        <datalist id="vaccine-list">
          <option value="วัคซีนรวมแมว (FVRCP)"><option value="พิษสุนัขบ้า">
          <option value="ลิวคีเมีย (FeLV)"><option value="FIP">
        </datalist>
      </label>
      <label>วันที่ฉีด *
        <input type="date" name="vaccine_date" required value="<?= e($_POST['vaccine_date'] ?? date('Y-m-d')) ?>">
      </label>
      <label>วันฉีดครั้งถัดไป
        <input type="date" name="next_due_date" value="<?= e($_POST['next_due_date'] ?? '') ?>">
      </label>
      <label>คลินิก / โรงพยาบาลสัตว์
        <input type="text" name="vet_clinic" value="<?= e($_POST['vet_clinic'] ?? '') ?>">
      </label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">บันทึกวัคซีน</button></div>
    </form>
  </details>
</section>

<!-- บันทึกน้ำหนัก + นัดหมอ -->
<section class="panel" id="records">
  <div class="panel-head">
    <h2>บันทึกน้ำหนักและการพบหมอ</h2>
  </div>

  <?php if (count($weights) >= 2): ?>
    <figure class="chart-wrap">
      <?= weight_chart($weights) ?>
      <figcaption>น้ำหนัก (กก.) ตามวันที่บันทึก</figcaption>
    </figure>
  <?php elseif (count($weights) === 1): ?>
    <p class="muted">บันทึกน้ำหนักอีกอย่างน้อย 1 ครั้งเพื่อดูกราฟ</p>
  <?php endif; ?>

  <?php if (!$records): ?>
    <p class="muted">ยังไม่มีบันทึกสุขภาพ เริ่มจากชั่งน้ำหนักน้องแมวแล้วบันทึกด้านล่าง</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>วันที่</th><th>น้ำหนัก</th><th>เหตุผลที่พบหมอ</th><th>ผลตรวจ / วินิจฉัย</th><th>นัดครั้งถัดไป</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($records as $r): ?>
          <tr>
            <td><?= e(thai_date($r['record_date'])) ?></td>
            <td><?= $r['weight_kg'] !== null ? e($r['weight_kg']) . ' กก.' : '-' ?></td>
            <td><?= e($r['appointment_reason'] ?: '-') ?></td>
            <td><?= nl2br(e($r['diagnosis'] ?: '-')) ?></td>
            <td>
              <?= e(thai_date($r['next_appointment_date'], true)) ?>
              <?php if ($r['vet_clinic'] && $r['next_appointment_date']): ?><br><small><?= e($r['vet_clinic']) ?></small><?php endif; ?>
            </td>
            <td>
              <form method="post" onsubmit="return confirm('ลบบันทึกนี้?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
                <button class="link-btn" type="submit">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <details class="add-form" <?= ($_POST['action'] ?? '') === 'add_record' ? 'open' : '' ?>>
    <summary class="btn btn-ghost">+ เพิ่มบันทึกสุขภาพ</summary>
    <form method="post" class="form form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_record">
      <label>วันที่ *
        <input type="date" name="record_date" required value="<?= e($_POST['record_date'] ?? date('Y-m-d')) ?>">
      </label>
      <label>น้ำหนัก (กก.)
        <input type="number" name="weight_kg" step="0.01" min="0.1" max="30" value="<?= e($_POST['weight_kg'] ?? '') ?>">
      </label>
      <label class="span-2">เหตุผลที่พบหมอ
        <input type="text" name="appointment_reason" placeholder="เช่น ตรวจสุขภาพประจำปี, ท้องเสีย" value="<?= e($_POST['appointment_reason'] ?? '') ?>">
      </label>
      <label class="span-2">ผลตรวจ / วินิจฉัย
        <textarea name="diagnosis" rows="3"><?= e($_POST['diagnosis'] ?? '') ?></textarea>
      </label>
      <label>วันเวลานัดครั้งถัดไป
        <input type="datetime-local" name="next_appointment_date" value="<?= e($_POST['next_appointment_date'] ?? '') ?>">
      </label>
      <label>คลินิก / โรงพยาบาลสัตว์
        <input type="text" name="vet_clinic" value="<?= e($_POST['vet_clinic'] ?? '') ?>">
      </label>
      <div class="form-actions span-2"><button class="btn btn-primary" type="submit">บันทึกข้อมูลสุขภาพ</button></div>
    </form>
  </details>
</section>
<?php require 'includes/footer.php'; ?>
