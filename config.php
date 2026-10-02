<?php
// =========================================================
// ตั้งค่าการเชื่อมต่อฐานข้อมูล (แก้ให้ตรงกับเครื่องตัวเอง)
// XAMPP ปกติ: user = root, password = ว่าง
// =========================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'cat_health_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('Asia/Bangkok');
session_start();

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    die('เชื่อมต่อฐานข้อมูลไม่ได้: ' . htmlspecialchars($ex->getMessage()));
}

// ---------- ฟังก์ชันช่วย ----------

// ป้องกัน XSS เวลาแสดงข้อความบนหน้าเว็บ
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// บังคับให้ล็อกอินก่อนเข้าหน้า
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// ดึงข้อมูลแมว เฉพาะที่เป็นของผู้ใช้ที่ล็อกอินอยู่
function get_own_cat(PDO $pdo, $cat_id) {
    $stmt = $pdo->prepare('SELECT * FROM cats WHERE cat_id = ? AND user_id = ?');
    $stmt->execute([(int)$cat_id, $_SESSION['user_id']]);
    return $stmt->fetch();
}

// แปลงวันที่เป็นแบบไทย เช่น 5 ต.ค. 2569
function thai_date($date, $with_time = false) {
    if (!$date) return '-';
    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $t = strtotime($date);
    $out = date('j', $t) . ' ' . $months[(int)date('n', $t)] . ' ' . (date('Y', $t) + 543);
    if ($with_time && date('H:i', $t) !== '00:00') {
        $out .= ' เวลา ' . date('H:i', $t) . ' น.';
    }
    return $out;
}

// คำนวณอายุแมวจากวันเกิด
function cat_age($birth_date) {
    if (!$birth_date) return '-';
    $diff = (new DateTime($birth_date))->diff(new DateTime());
    if ($diff->y > 0) return $diff->y . ' ปี ' . $diff->m . ' เดือน';
    return $diff->m . ' เดือน';
}

// ที่อยู่รูปแมว (ถ้าไม่มีรูป ใช้รูปเริ่มต้น)
function cat_photo($photo) {
    if ($photo && file_exists(__DIR__ . '/uploads/' . $photo)) {
        return 'uploads/' . rawurlencode($photo);
    }
    return 'assets/cat-default.svg';
}

// ข้อความแจ้งเตือนแบบครั้งเดียว (flash message)
function flash($msg = null, $type = 'ok') {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// CSRF token สำหรับฟอร์ม POST
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die('คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
}

// นับจำนวนการแจ้งเตือน (นัดหมอ + วัคซีนที่ใกล้ถึง/เลยกำหนด ภายใน 7 วัน)
function count_alerts(PDO $pdo) {
    $sql = "SELECT
              (SELECT COUNT(*) FROM health_records h JOIN cats c ON c.cat_id = h.cat_id
               WHERE c.user_id = :u1 AND h.next_appointment_date IS NOT NULL
                 AND DATE(h.next_appointment_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
            + (SELECT COUNT(*) FROM vaccinations v JOIN cats c ON c.cat_id = v.cat_id
               WHERE c.user_id = :u2 AND v.next_due_date IS NOT NULL
                 AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                 AND NOT EXISTS (SELECT 1 FROM vaccinations v2
                                 WHERE v2.cat_id = v.cat_id AND v2.vaccine_name = v.vaccine_name
                                   AND v2.vaccine_date > v.vaccine_date))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u1' => $_SESSION['user_id'], ':u2' => $_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}
