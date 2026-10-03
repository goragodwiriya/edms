-- ---------------------------------------------------------------------------
-- modules/dms/install/database.sql — ตารางที่โมดูล dms (ระบบจัดเก็บเอกสาร) เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- ประกาศสองที่ = ติดตั้งใหม่ล้มด้วย "Table already exists" และนิยามสองชุด
-- จะค่อย ๆ ต่างกันจนไซต์ที่อัปเกรดคนละเส้นทางได้สคีมาไม่เหมือนกัน
--
-- ทั้งการติดตั้งใหม่ (common.php::schemaFiles) และการปรับรุ่น (ensureTable)
-- อ่านนิยามจากไฟล์นี้ไฟล์เดียว
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- dms — เอกสาร
--
-- create_date คือ "วันที่ของเอกสาร" ที่ผู้ใช้กรอกเอง ไม่ใช่เวลาที่สร้างแถว
-- url ว่าง = เอกสารแบบแนบไฟล์ (ไฟล์อยู่ใน dms_files) · ไม่ว่าง = เอกสารแบบลิงก์
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_dms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `create_date` date NOT NULL,
  `document_no` varchar(20) NOT NULL,
  `detail` text NOT NULL,
  `topic` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `document_no` (`document_no`),
  KEY `create_date` (`create_date`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- dms_files — ไฟล์แนบของเอกสาร เก็บที่ datas/<file> (dms/<dms_id>/<uniqid>.<ext>)
-- topic = ชื่อไฟล์ต้นฉบับ (ไม่มีนามสกุล) · name = ชื่อที่ใช้ตอนดาวน์โหลด
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_dms_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dms_id` int(11) NOT NULL,
  `topic` varchar(150) NOT NULL,
  `name` varchar(150) NOT NULL,
  `ext` varchar(4) NOT NULL,
  `size` int(11) NOT NULL,
  `file` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dms_id` (`dms_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- dms_meta — หมวดหมู่ของเอกสาร type = department (หลายค่าได้) | cabinet
-- value คือ category_id ของตาราง category ที่ type เดียวกัน
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_dms_meta` (
  `dms_id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `value` text NOT NULL,
  KEY `idx_dms_type` (`dms_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- dms_download — จำนวนครั้งที่สมาชิกแต่ละคนดาวน์โหลดไฟล์แต่ละไฟล์
-- file_id = 0 คือการเปิดลิงก์ของเอกสารแบบ URL · updated_at = ครั้งล่าสุด
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_dms_download` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `dms_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `downloads` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_download` (`dms_id`,`file_id`,`member_id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- ตู้เก็บเอกสารตั้งต้น — cabinet เป็นหมวดหมู่ที่มีแค่โมดูล dms ใช้
-- (department เป็นหมวดหมู่ของระบบกลาง อยู่ใน install/database.sql ของโปรเจ็ค)
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_category` (`type`, `category_id`, `topic`, `color`, `is_active`) VALUES
('cabinet', '1', 'คำสั่ง', NULL, 1),
('cabinet', '2', 'คู่มือ', NULL, 1),
('cabinet', '3', 'ทรัพย์สิน', NULL, 1);
