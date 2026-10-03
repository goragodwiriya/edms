<?php
/**
 * install/testdb-module.php — ข้อมูลทดสอบของระบบจัดเก็บเอกสาร (dms) สำหรับ install/cli-testdb.php
 *
 * ชุดทั่วไป (F2/F4/F5/F6): ใส่เอกสาร ไฟล์ หมวดหมู่ และประวัติดาวน์โหลด เพื่อให้
 * การนับแถวก่อน/หลังปรับรุ่นครอบตาราง dms* ด้วย
 *
 * ชุด F3: ตาราง dms* หน้าตาแบบ edms รุ่น 6.9 (ตัวที่อัปเกรดมาจริง) — MyISAM + utf8mb3,
 * PRIMARY KEY/AUTO_INCREMENT เติมด้วย ALTER, dms_files.create_date,
 * dms_download.last_update, ดัชนีเดี่ยวของ dms_meta และเลขที่เอกสารที่ number.type
 * ยังเป็น 'dms_format_no' — ตัวปรับรุ่นต้องพาไปถึงสคีมาปัจจุบันโดยไม่ทำข้อมูลหาย
 */
if (!defined('ROOT_PATH')) {
    exit;
}

function testdbSeedModule($db, $prefix, array &$notes, $fixture = '')
{
    if ($fixture === 'f3') {
        testdbDmsLegacyState($db, $prefix, $notes);

        return;
    }
    $now = date('Y-m-d H:i:s');
    $db->insert($prefix.'_dms', [
        'member_id' => 1, 'create_date' => date('Y-m-d'), 'document_no' => 'DOC-TEST-0001',
        'detail' => 'รายละเอียด', 'topic' => 'เอกสารทดสอบ', 'url' => ''
    ]);
    $db->insert($prefix.'_dms', [
        'member_id' => 1, 'create_date' => date('Y-m-d'), 'document_no' => 'DOC-TEST-0002',
        'detail' => '', 'topic' => 'ลิงก์ทดสอบ', 'url' => 'https://example.com/doc'
    ]);
    $db->insert($prefix.'_dms_files', [
        'dms_id' => 1, 'topic' => 'ไฟล์ทดสอบ', 'name' => 'ไฟล์ทดสอบ', 'ext' => 'pdf',
        'size' => 1234, 'file' => 'dms/1/test.pdf', 'created_at' => $now
    ]);
    foreach ([[1, 'department', '1'], [1, 'department', '2'], [1, 'cabinet', '1'], [2, 'department', '1'], [2, 'cabinet', '2']] as $meta) {
        $db->insert($prefix.'_dms_meta', ['dms_id' => $meta[0], 'type' => $meta[1], 'value' => $meta[2]]);
    }
    $db->insert($prefix.'_dms_download', ['file_id' => 1, 'dms_id' => 1, 'member_id' => 1, 'downloads' => 3, 'updated_at' => $now]);
    $db->insert($prefix.'_dms_download', ['file_id' => 0, 'dms_id' => 2, 'member_id' => 1, 'downloads' => 1, 'updated_at' => $now]);
    $notes[] = 'dms: เอกสาร 2 ไฟล์ 1 หมวดหมู่ 5 ประวัติดาวน์โหลด 2';
}

/**
 * ตาราง dms* แบบ edms 6.9 พร้อมข้อมูล
 *
 * @param Db     $db
 * @param string $prefix
 * @param array  $notes
 */
function testdbDmsLegacyState($db, $prefix, array &$notes)
{
    foreach (['dms', 'dms_files', 'dms_meta', 'dms_download'] as $table) {
        $db->query("DROP TABLE IF EXISTS `{$prefix}_$table`");
    }
    // คำสั่งชุดเดียวกับ install/database.sql ของ edms 6.9 (phpMyAdmin dump)
    $db->query("CREATE TABLE `{$prefix}_dms` (`id` int(11) NOT NULL, `member_id` int(11) NOT NULL, `create_date` date NOT NULL,
        `document_no` varchar(20) NOT NULL, `detail` text NOT NULL, `topic` varchar(255) NOT NULL, `url` varchar(255) NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    $db->query("CREATE TABLE `{$prefix}_dms_download` (`id` int(11) NOT NULL, `file_id` int(11) NOT NULL, `dms_id` int(11) NOT NULL,
        `member_id` int(11) NOT NULL, `downloads` int(11) NOT NULL, `last_update` datetime NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    $db->query("CREATE TABLE `{$prefix}_dms_files` (`id` int(11) NOT NULL, `dms_id` int(11) NOT NULL, `topic` varchar(150) NOT NULL,
        `name` varchar(150) NOT NULL, `ext` varchar(4) NOT NULL, `size` int(11) NOT NULL, `file` varchar(50) DEFAULT NULL,
        `create_date` datetime NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    $db->query("CREATE TABLE `{$prefix}_dms_meta` (`dms_id` int(11) NOT NULL, `type` varchar(20) NOT NULL, `value` text NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    $db->query("ALTER TABLE `{$prefix}_dms` ADD PRIMARY KEY (`id`)");
    $db->query("ALTER TABLE `{$prefix}_dms_download` ADD PRIMARY KEY (`id`), ADD KEY `dms_id` (`dms_id`), ADD KEY `member_id` (`member_id`)");
    $db->query("ALTER TABLE `{$prefix}_dms_files` ADD PRIMARY KEY (`id`), ADD KEY `dms_id` (`dms_id`)");
    $db->query("ALTER TABLE `{$prefix}_dms_meta` ADD KEY `dms_id` (`dms_id`), ADD KEY `type` (`type`)");
    // ตัวปรับรุ่นรุ่นเก่าไม่เคยรัน ALTER ... AUTO_INCREMENT ท้ายไฟล์ — จำลองไซต์ที่ไม่มี AUTO_INCREMENT
    $db->query("INSERT INTO `{$prefix}_dms` VALUES
        (1, 1, '2022-12-24', 'DOC651224-0001', 'รายละเอียดเดิม', 'เอกสารรุ่นก่อน', ''),
        (2, 1, '2022-12-25', 'DOC651225-0002', '', 'ลิงก์รุ่นก่อน', 'https://example.com/old')");
    $db->query("INSERT INTO `{$prefix}_dms_files` VALUES
        (1, 1, 'edms', 'edms', 'pdf', 36457, 'dms/1/63a70ab6796e1.pdf', '2022-12-24 21:20:38'),
        (2, 1, 'จองห้อง_3600', 'จองห้อง_3600', 'pdf', 49072, 'dms/1/63a70af338e92.pdf', '2022-12-24 21:21:39')");
    $db->query("INSERT INTO `{$prefix}_dms_meta` VALUES (1, 'department', '3'), (1, 'department', '1'), (1, 'cabinet', '1'),
        (2, 'department', '2'), (2, 'cabinet', '2')");
    $db->query("INSERT INTO `{$prefix}_dms_download` VALUES (1, 1, 1, 1, 2, '2022-12-26 08:00:00'), (2, 0, 2, 1, 1, '2022-12-26 09:00:00')");
    // running number ของระบบเดิม: type = ชื่อค่ากำหนด ไม่ใช่รูปแบบเลข
    $db->query("INSERT INTO `{$prefix}_number` (`type`, `prefix`, `auto_increment`) VALUES ('dms_format_no', 'DOC651225-', 2)");
    // user.password ของ edms 6.9 เป็น varchar(50) และเก็บรหัสผ่านแบบ sha1 (40 ตัว)
    // ตัวปรับรุ่นต้องเก็บ bcrypt (60 ตัว) ของผู้ดูแลได้ครบ ไม่งั้นเข้าระบบไม่ได้อีกเลย
    $db->query("ALTER TABLE `{$prefix}_user` MODIFY `password` varchar(50) NOT NULL");
    $db->query("UPDATE `{$prefix}_user` SET `password` = SHA1(CONCAT('admin', `salt`)) WHERE `id` = 1");
    $notes[] = 'dms (f3): ตาราง dms* แบบ edms 6.9 (MyISAM/utf8mb3, create_date, last_update, ไม่มี AUTO_INCREMENT) เอกสาร 2 ไฟล์ 2 · number.type = dms_format_no · user.password varchar(50) + sha1';
}

/**
 * ข้อยืนยันหลังปรับรุ่น (ว่าง = ผ่าน)
 *
 * @param Db     $db
 * @param string $prefix
 * @param string $fixture
 *
 * @return array
 */
function testdbAfterUpgrade($db, $prefix, $fixture)
{
    $problems = [];
    foreach (['dms', 'dms_files', 'dms_download'] as $table) {
        if (!isAutoIncrement($db, $prefix.'_'.$table, 'id')) {
            $problems[] = $table.'.id ไม่เป็น AUTO_INCREMENT';
        }
    }
    $cabinet = $db->customQuery("SELECT COUNT(*) AS `c` FROM `{$prefix}_category` WHERE `type` = 'cabinet'");
    if (empty($cabinet) || (int) $cabinet[0]->c === 0) {
        $problems[] = 'ไม่มีตู้เก็บเอกสารตั้งต้น';
    }
    if ($fixture !== 'f3') {
        return $problems;
    }
    $admin = $db->customQuery("SELECT `password` FROM `{$prefix}_user` WHERE `id` = 1");
    if (empty($admin) || strlen($admin[0]->password) !== 60 || strpos($admin[0]->password, '$2y$') !== 0) {
        $problems[] = 'รหัสผ่านของผู้ดูแลหลังปรับรุ่นไม่ใช่ bcrypt ครบ 60 ตัว (ยาว '
            .(empty($admin) ? 0 : strlen($admin[0]->password)).') — ผู้ดูแลจะเข้าระบบไม่ได้';
    }
    $file =$db->customQuery("SELECT `created_at` FROM `{$prefix}_dms_files` WHERE `id` = 2");
    if (empty($file) || $file[0]->created_at !== '2022-12-24 21:21:39') {
        $problems[] = 'dms_files.create_date ไม่ถูกย้ายไป created_at';
    }
    $download = $db->customQuery("SELECT `updated_at`, `downloads` FROM `{$prefix}_dms_download` WHERE `id` = 1");
    if (empty($download) || $download[0]->updated_at !== '2022-12-26 08:00:00' || (int) $download[0]->downloads !== 2) {
        $problems[] = 'dms_download.last_update ไม่ถูกย้ายไป updated_at';
    }
    $number = $db->customQuery("SELECT `type`, `auto_increment` FROM `{$prefix}_number` WHERE `prefix` = 'DOC651225-'");
    if (count($number) !== 1 || $number[0]->type === 'dms_format_no' || (int) $number[0]->auto_increment !== 2) {
        $problems[] = 'number.type ของเลขที่เอกสารไม่ถูกแปลงเป็นรูปแบบเลข';
    }
    $meta = $db->customQuery("SELECT COUNT(*) AS `c` FROM `{$prefix}_dms_meta`");
    if ((int) $meta[0]->c !== 5) {
        $problems[] = 'จำนวน dms_meta ไม่ครบ';
    }
    foreach (['dms_id', 'type'] as $index) {
        if ($db->indexExists($prefix.'_dms_meta', $index)) {
            $problems[] = 'dms_meta ยังมีดัชนีรุ่นเก่า '.$index;
        }
    }
    // เพิ่มเอกสารใหม่ได้จริง (AUTO_INCREMENT ต่อจากของเดิม)
    $id = $db->insert($prefix.'_dms', [
        'member_id' => 1, 'create_date' => '2026-01-01', 'document_no' => 'AFTER-UPGRADE',
        'detail' => '', 'topic' => 'หลังปรับรุ่น'
    ]);
    if ((int) $id !== 3) {
        $problems[] = 'เพิ่มเอกสารใหม่หลังปรับรุ่นได้ id '.var_export($id, true).' (ควรเป็น 3)';
    }
    $db->query("DELETE FROM `{$prefix}_dms` WHERE `document_no` = 'AFTER-UPGRADE'");

    return $problems;
}
