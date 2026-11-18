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

-- ---------------------------------------------------------------------------
-- เอกสารตัวอย่าง — ลงเฉพาะการติดตั้งใหม่ ตัวปรับรุ่น (ensureTable) ไม่รัน INSERT
-- ไซต์ที่ปรับรุ่นจึงไม่ได้แถวเหล่านี้
--
-- เป็นเอกสารแบบลิงก์ทั้งหมด เพราะเอกสารแบบแนบไฟล์ต้องมีไฟล์จริงที่ datas/dms/<id>/
-- ซึ่ง SQL ส่งไปด้วยไม่ได้ ถ้าใส่แถว dms_files ปุ่มดาวน์โหลดจะพาไปหน้า 404
--
-- วันที่นับถอยหลังจากวันติดตั้ง เพื่อให้การ์ด "เอกสารใหม่ 30 วัน" บนแดชบอร์ดมีตัวเลข
-- เลขที่ SAMPLE-xxxx อยู่นอกรูปแบบเลขจริง จึงไม่ชนกับเลขที่ระบบออกให้
-- member_id = 1 คือผู้ดูแลสูงสุดที่ตัวติดตั้งสร้างหลังรันไฟล์ SQL
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_dms` (`id`, `member_id`, `create_date`, `document_no`, `detail`, `topic`, `url`) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 210 DAY), 'SAMPLE-0001', 'แต่งตั้งคณะกรรมการรับผิดชอบการจัดซื้อจัดจ้างของหน่วยงาน', 'คำสั่งแต่งตั้งคณะกรรมการจัดซื้อจัดจ้างประจำปี', 'https://example.com/edms/purchasing-committee-order'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 180 DAY), 'SAMPLE-0002', 'วิธีค้นหา ดาวน์โหลด และอัปโหลดเอกสารในระบบ', 'คู่มือการใช้งานระบบจัดเก็บเอกสาร', 'https://example.com/edms/user-manual'),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 150 DAY), 'SAMPLE-0003', 'รายการครุภัณฑ์สำนักงานพร้อมสถานที่จัดเก็บ', 'ทะเบียนครุภัณฑ์สำนักงาน', 'https://example.com/edms/asset-register'),
(4, 1, DATE_SUB(CURDATE(), INTERVAL 120 DAY), 'SAMPLE-0004', 'ขั้นตอนตั้งแต่การขอซื้อจนถึงการตรวจรับพัสดุ', 'คู่มือขั้นตอนการจัดซื้อจัดจ้าง', 'https://example.com/edms/purchasing-manual'),
(5, 1, DATE_SUB(CURDATE(), INTERVAL 95 DAY), 'SAMPLE-0005', 'มอบหมายผู้รับผิดชอบงานทะเบียนประวัติและงานสวัสดิการ', 'คำสั่งมอบหมายผู้รับผิดชอบงานบุคคล', 'https://example.com/edms/hr-assignment-order'),
(6, 1, DATE_SUB(CURDATE(), INTERVAL 70 DAY), 'SAMPLE-0006', 'ข้อมูลที่พนักงานใหม่ควรรู้ในสัปดาห์แรกของการทำงาน', 'คู่มือสำหรับพนักงานใหม่', 'https://example.com/edms/new-employee-handbook'),
(7, 1, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'SAMPLE-0007', 'แบบฟอร์มสำหรับขอเบิกวัสดุสำนักงาน', 'แบบฟอร์มขอเบิกวัสดุสำนักงาน', 'https://example.com/edms/supply-request-form'),
(8, 1, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'SAMPLE-0008', 'วันหยุดประจำปีของหน่วยงาน', 'ประกาศวันหยุดประจำปี', 'https://example.com/edms/annual-holidays'),
(9, 1, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'SAMPLE-0009', 'สรุปผลการตรวจนับทรัพย์สินประจำปี', 'รายงานตรวจนับทรัพย์สินประจำปี', 'https://example.com/edms/asset-count-report'),
(10, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'SAMPLE-0010', 'หลักเกณฑ์การลาและลำดับการขออนุมัติ', 'แนวปฏิบัติการลาและการขออนุมัติ', 'https://example.com/edms/leave-guideline'),
(11, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'SAMPLE-0011', 'แต่งตั้งคณะทำงานดูแลระบบจัดเก็บเอกสาร', 'คำสั่งแต่งตั้งคณะทำงานระบบเอกสารอิเล็กทรอนิกส์', 'https://example.com/edms/edms-working-group-order');

INSERT INTO `{prefix}_dms_meta` (`dms_id`, `type`, `value`) VALUES
(1, 'cabinet', '1'), (1, 'department', '1'), (1, 'department', '2'),
(2, 'cabinet', '2'), (2, 'department', '1'), (2, 'department', '2'), (2, 'department', '3'),
(3, 'cabinet', '3'), (3, 'department', '1'), (3, 'department', '2'),
(4, 'cabinet', '2'), (4, 'department', '2'),
(5, 'cabinet', '1'), (5, 'department', '3'),
(6, 'cabinet', '2'), (6, 'department', '3'),
(7, 'cabinet', '3'), (7, 'department', '1'), (7, 'department', '2'), (7, 'department', '3'),
(8, 'cabinet', '1'), (8, 'department', '1'), (8, 'department', '2'), (8, 'department', '3'),
(9, 'cabinet', '3'), (9, 'department', '1'), (9, 'department', '2'),
(10, 'cabinet', '2'), (10, 'department', '3'),
(11, 'cabinet', '1'), (11, 'department', '1');

-- file_id = 0 คือการเปิดลิงก์ของเอกสารแบบ URL
INSERT INTO `{prefix}_dms_download` (`file_id`, `dms_id`, `member_id`, `downloads`, `updated_at`) VALUES
(0, 1, 1, 3, DATE_SUB(NOW(), INTERVAL 200 DAY)),
(0, 2, 1, 5, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(0, 8, 1, 1, DATE_SUB(NOW(), INTERVAL 20 DAY));
