-- =========================================================
-- Cat Health Tracker (ระบบบันทึกสุขภาพแมว)
-- ฐานข้อมูล MySQL / MariaDB
-- วิธีใช้: เปิด phpMyAdmin > แท็บ Import > เลือกไฟล์นี้ > Go
-- =========================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS cat_health_tracker
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cat_health_tracker;

-- ---------------------------------------------------------
-- ตาราง Users (สำหรับหน้า Login)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ตาราง Cats (ข้อมูลแมว)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS cats (
  cat_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,                       -- เจ้าของ (FK -> users)
  cat_name   VARCHAR(100) NOT NULL,
  breed      VARCHAR(100),
  birth_date DATE,
  gender     ENUM('ผู้','เมีย') NOT NULL,
  owner_name VARCHAR(100),
  photo      VARCHAR(255),                       -- ชื่อไฟล์รูปในโฟลเดอร์ uploads/
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ตาราง Vaccinations (ประวัติวัคซีน)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS vaccinations (
  vaccine_id    INT AUTO_INCREMENT PRIMARY KEY,
  cat_id        INT NOT NULL,
  vaccine_name  VARCHAR(100) NOT NULL,
  vaccine_date  DATE NOT NULL,
  next_due_date DATE,
  vet_clinic    VARCHAR(150),
  FOREIGN KEY (cat_id) REFERENCES cats(cat_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ตาราง HealthRecords (บันทึกน้ำหนัก / นัดหมอ)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS health_records (
  record_id             INT AUTO_INCREMENT PRIMARY KEY,
  cat_id                INT NOT NULL,
  record_date           DATE NOT NULL,
  appointment_reason    VARCHAR(255),            -- เหตุผลที่พบหมอ
  diagnosis             TEXT,                    -- ผลตรวจ / วินิจฉัย
  next_appointment_date DATETIME,                -- วันเวลานัดครั้งถัดไป
  vet_clinic            VARCHAR(150),            -- สถานที่ (ใช้ในข้อความแจ้งเตือน)
  weight_kg             DECIMAL(4,2),
  FOREIGN KEY (cat_id) REFERENCES cats(cat_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ข้อมูลตัวอย่าง (ลบส่วนนี้ได้ถ้าไม่ต้องการ)
-- บัญชีทดลอง: username = demo / password = 1234
-- ---------------------------------------------------------
INSERT INTO users (user_id, username, email, password_hash, full_name) VALUES
(1, 'demo', 'demo@example.com', '$2y$10$QMjxTsOgBmz2wKKHprxzvOD5uc7k2R4N9bu7t1Dr/si.NEd1ia5QO', 'สมใจ รักแมว');

INSERT INTO cats (cat_id, user_id, cat_name, breed, birth_date, gender, owner_name) VALUES
(1, 1, 'มะลิ',  'สก็อตติชโฟลด์', '2022-03-14', 'เมีย', 'สมใจ รักแมว'),
(2, 1, 'ข้าวตู', 'วิเชียรมาศ',   '2021-08-02', 'ผู้',  'สมใจ รักแมว'),
(3, 1, 'โมจิ',  'เปอร์เซีย',    '2023-11-20', 'ผู้',  'สมใจ รักแมว');

INSERT INTO vaccinations (cat_id, vaccine_name, vaccine_date, next_due_date, vet_clinic) VALUES
(1, 'วัคซีนรวมแมว (FVRCP)', DATE_SUB(CURDATE(), INTERVAL 11 MONTH), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'โรงพยาบาลสัตว์ทองหล่อ'),
(1, 'พิษสุนัขบ้า',          DATE_SUB(CURDATE(), INTERVAL 6 MONTH),  DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'โรงพยาบาลสัตว์ทองหล่อ'),
(2, 'วัคซีนรวมแมว (FVRCP)', DATE_SUB(CURDATE(), INTERVAL 13 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'คลินิกหมอแมวบางนา'),
(3, 'ลิวคีเมีย (FeLV)',     DATE_SUB(CURDATE(), INTERVAL 2 MONTH),  DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 'คลินิกหมอแมวบางนา');

INSERT INTO health_records (cat_id, record_date, appointment_reason, diagnosis, next_appointment_date, vet_clinic, weight_kg) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 90 DAY), 'ตรวจสุขภาพประจำปี', 'สุขภาพแข็งแรงดี', NULL, 'โรงพยาบาลสัตว์ทองหล่อ', 3.60),
(1, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'ชั่งน้ำหนัก', NULL, NULL, NULL, 3.75),
(1, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  'ตาแฉะ', 'เยื่อบุตาอักเสบเล็กน้อย ให้ยาหยอดตา', CONCAT(DATE_ADD(CURDATE(), INTERVAL 3 DAY), ' 10:30:00'), 'โรงพยาบาลสัตว์ทองหล่อ', 3.80),
(2, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'ตรวจฟัน', 'หินปูนเล็กน้อย', CONCAT(DATE_ADD(CURDATE(), INTERVAL 20 DAY), ' 14:00:00'), 'คลินิกหมอแมวบางนา', 5.20),
(3, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'ชั่งน้ำหนัก', NULL, NULL, NULL, 2.90);
