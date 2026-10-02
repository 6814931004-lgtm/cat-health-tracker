# Cat Health Tracker (ระบบบันทึกสุขภาพแมว)

โปรเจกต์วิชาการเขียนโปรแกรมเว็บ — PHP + MySQL

## วิธีติดตั้ง (XAMPP)
1. คัดลอกโฟลเดอร์ `cat-health-tracker` ไปไว้ใน `C:\xampp\htdocs\`
2. เปิด XAMPP Control Panel แล้วกด Start ที่ **Apache** และ **MySQL**
3. เข้า http://localhost/phpmyadmin → แท็บ **Import** → เลือกไฟล์ `database.sql` → กด **Go**
4. เปิด http://localhost/cat-health-tracker/login.php
5. ล็อกอินด้วยบัญชีทดลอง **demo / 1234** หรือกดสมัครสมาชิกใหม่

ถ้า MySQL มีรหัสผ่าน ให้แก้ `DB_PASS` ใน `config.php`

## โครงสร้างไฟล์
| ไฟล์ | หน้าที่ |
|---|---|
| `database.sql` | สร้างฐานข้อมูล 4 ตาราง + ข้อมูลตัวอย่าง |
| `config.php` | เชื่อมต่อฐานข้อมูล + ฟังก์ชันช่วย (วันที่ไทย, คำนวณอายุ, CSRF ฯลฯ) |
| `login.php` / `register.php` / `forgot_password.php` / `logout.php` | ระบบสมาชิก |
| `index.php` | หน้ารายชื่อแมว (รูปวงกลม + ชื่อ) |
| `cat_form.php` | เพิ่ม / แก้ไขบัญชีแมว + อัปโหลดรูป |
| `cat_delete.php` | ลบบัญชีแมว (ลบวัคซีนและบันทึกตามอัตโนมัติ) |
| `cat_detail.php` | ข้อมูลแมว, ประวัติวัคซีน, กราฟน้ำหนัก, บันทึกการพบหมอ |
| `notifications.php` | แจ้งเตือนวันนัดหมอและวัคซีนที่ใกล้ถึง/เลยกำหนด |
| `report.php` | ข้อมูลทั้งหมด / รายงานสรุป (พิมพ์ได้) |
| `includes/` | ส่วนหัว-ท้ายที่ใช้ร่วมกันทุกหน้า |
| `assets/` | CSS, โลโก้, รูปแมวเริ่มต้น |
| `uploads/` | เก็บรูปแมวที่อัปโหลด |

## ฐานข้อมูล
- **users** — user_id (PK), username, email, password_hash, full_name
- **cats** — cat_id (PK), user_id (FK), cat_name, breed, birth_date, gender, owner_name, photo
- **vaccinations** — vaccine_id (PK), cat_id (FK), vaccine_name, vaccine_date, next_due_date, vet_clinic
- **health_records** — record_id (PK), cat_id (FK), record_date, appointment_reason, diagnosis, next_appointment_date, vet_clinic, weight_kg

ส่วนที่เพิ่มจากภาพร่าง: `cats.user_id` (ให้แต่ละคนเห็นเฉพาะแมวตัวเอง), `cats.photo` (รูปแมว),
`health_records.vet_clinic` (ใช้เป็น "สถานที่" ในข้อความแจ้งเตือน)

## ความปลอดภัยที่ใส่ไว้
- รหัสผ่านเข้ารหัสด้วย `password_hash()`
- ใช้ Prepared Statement ทุก query (กัน SQL Injection)
- `htmlspecialchars()` ทุกจุดที่แสดงข้อมูล (กัน XSS)
- CSRF token ในทุกฟอร์ม POST
- ตรวจว่าแมวเป็นของผู้ใช้ที่ล็อกอินก่อนแสดง/แก้ไข/ลบ
- ตรวจชนิดและขนาดไฟล์รูป และห้ามรันสคริปต์ในโฟลเดอร์ uploads

หมายเหตุ: หน้าลืมรหัสผ่านใช้วิธียืนยัน "ชื่อผู้ใช้ + อีเมล" แบบง่ายสำหรับงานเรียน
ระบบจริงควรส่งลิงก์รีเซ็ตทางอีเมลแทน
