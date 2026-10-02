<?php
// หน้าเดียวใช้ได้ทั้ง "เพิ่มแมว" (ไม่มี id) และ "แก้ไขแมว" (มี ?id=)
require 'config.php';
require_login();

$cat_id = (int)($_GET['id'] ?? $_POST['cat_id'] ?? 0);
$is_edit = $cat_id > 0;

$cat = [
    'cat_name' => '', 'breed' => '', 'birth_date' => '',
    'gender' => 'เมีย', 'owner_name' => $_SESSION['full_name'], 'photo' => null,
];

if ($is_edit) {
    $found = get_own_cat($pdo, $cat_id);
    if (!$found) {
        flash('ไม่พบข้อมูลแมวตัวนี้', 'error');
        header('Location: index.php');
        exit;
    }
    $cat = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $cat['cat_name']   = trim($_POST['cat_name'] ?? '');
    $cat['breed']      = trim($_POST['breed'] ?? '');
    $cat['birth_date'] = $_POST['birth_date'] ?? '';
    $cat['gender']     = ($_POST['gender'] ?? '') === 'ผู้' ? 'ผู้' : 'เมีย';
    $cat['owner_name'] = trim($_POST['owner_name'] ?? '');

    if ($cat['cat_name'] === '') $errors[] = 'กรุณากรอกชื่อแมว';
    if ($cat['birth_date'] !== '' && strtotime($cat['birth_date']) > time()) {
        $errors[] = 'วันเกิดต้องไม่เป็นวันในอนาคต';
    }

    // ---------- อัปโหลดรูป ----------
    $new_photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $info = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;
        $mime = $info['mime'] ?? '';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'อัปโหลดรูปไม่สำเร็จ ลองเลือกไฟล์ใหม่';
        } elseif (!isset($allowed[$mime])) {
            $errors[] = 'รองรับเฉพาะรูป JPG, PNG, WEBP หรือ GIF';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = 'รูปต้องมีขนาดไม่เกิน 3 MB';
        } else {
            $new_photo = 'cat_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        }
    }

    if (!$errors) {
        if ($new_photo) {
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/uploads/' . $new_photo);
            // ลบรูปเก่าทิ้ง
            if ($is_edit && $cat['photo'] && file_exists(__DIR__ . '/uploads/' . $cat['photo'])) {
                unlink(__DIR__ . '/uploads/' . $cat['photo']);
            }
            $cat['photo'] = $new_photo;
        }

        $params = [
            $cat['cat_name'], $cat['breed'] ?: null, $cat['birth_date'] ?: null,
            $cat['gender'], $cat['owner_name'] ?: null, $cat['photo'],
        ];

        if ($is_edit) {
            $stmt = $pdo->prepare('UPDATE cats SET cat_name=?, breed=?, birth_date=?, gender=?, owner_name=?, photo=?
                                   WHERE cat_id=? AND user_id=?');
            $stmt->execute([...$params, $cat_id, $_SESSION['user_id']]);
            flash('บันทึกการแก้ไขข้อมูล' . $cat['cat_name'] . 'แล้ว');
        } else {
            $stmt = $pdo->prepare('INSERT INTO cats (cat_name, breed, birth_date, gender, owner_name, photo, user_id)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([...$params, $_SESSION['user_id']]);
            $cat_id = $pdo->lastInsertId();
            flash('เพิ่ม' . $cat['cat_name'] . 'เข้าระบบแล้ว');
        }
        header('Location: cat_detail.php?id=' . $cat_id);
        exit;
    }
}

$page_title = $is_edit ? 'แก้ไขข้อมูลแมว' : 'เพิ่มแมว';
require 'includes/header.php';
?>
<div class="page-head">
  <h1><?= $is_edit ? 'แก้ไขข้อมูล ' . e($cat['cat_name']) : 'เพิ่มแมวตัวใหม่' ?></h1>
</div>

<section class="panel narrow">
  <?php if ($errors): ?>
    <ul class="form-error" role="alert"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="cat_id" value="<?= $cat_id ?>">

    <div class="photo-pick">
      <img id="preview" class="avatar avatar-lg" src="<?= e(cat_photo($cat['photo'])) ?>" alt="" width="120" height="120">
      <label class="btn btn-ghost">
        <?= $cat['photo'] ? 'เปลี่ยนรูป' : 'เลือกรูปแมว' ?>
        <input type="file" name="photo" accept="image/*" class="visually-hidden"
               onchange="if(this.files[0]) document.getElementById('preview').src = URL.createObjectURL(this.files[0])">
      </label>
    </div>

    <label>ชื่อแมว *
      <input type="text" name="cat_name" value="<?= e($cat['cat_name']) ?>" required maxlength="100">
    </label>
    <label>สายพันธุ์
      <input type="text" name="breed" value="<?= e($cat['breed']) ?>" maxlength="100" list="breeds">
      <datalist id="breeds">
        <option value="วิเชียรมาศ"><option value="ขาวมณี"><option value="โคราช"><option value="ศุภลักษณ์">
        <option value="เปอร์เซีย"><option value="สก็อตติชโฟลด์"><option value="บริติชช็อตแฮร์">
        <option value="เมนคูน"><option value="อเมริกันช็อตแฮร์"><option value="ไทยพื้นบ้าน">
      </datalist>
    </label>
    <div class="form-row">
      <label>วันเกิด
        <input type="date" name="birth_date" value="<?= e($cat['birth_date']) ?>" max="<?= date('Y-m-d') ?>">
      </label>
      <fieldset class="radio-group">
        <legend>เพศ *</legend>
        <label><input type="radio" name="gender" value="ผู้" <?= $cat['gender'] === 'ผู้' ? 'checked' : '' ?>> ผู้</label>
        <label><input type="radio" name="gender" value="เมีย" <?= $cat['gender'] === 'เมีย' ? 'checked' : '' ?>> เมีย</label>
      </fieldset>
    </div>
    <label>ชื่อเจ้าของ
      <input type="text" name="owner_name" value="<?= e($cat['owner_name']) ?>" maxlength="100">
    </label>

    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= $is_edit ? 'cat_detail.php?id=' . $cat_id : 'index.php' ?>">ยกเลิก</a>
      <button type="submit" class="btn btn-primary"><?= $is_edit ? 'บันทึกการแก้ไข' : 'เพิ่มแมว' ?></button>
    </div>
  </form>
</section>
<?php require 'includes/footer.php'; ?>
