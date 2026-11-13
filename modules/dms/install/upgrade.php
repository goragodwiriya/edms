<?php
/**
 * modules/dms/install/upgrade.php — พาฐานของ edms รุ่นเดิมมาถึงสคีมาของโมดูล dms
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้คือชุดเดียวกับที่
 * upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ก่อนมีไฟล์นี้ งานทั้งหมดนี้ฝังอยู่ใน install/upgrade2.php ของโปรเจ็ค
 * และนิยามตาราง dms* อยู่ใน install/database.sql ของโปรเจ็ค ตอนนี้นิยามอยู่ที่
 * modules/dms/install/database.sql ที่เดียว — ถอดโมดูลออก = ไม่มีตารางของมัน
 *
 * สิ่งที่ต้องพาข้ามมาให้ได้ (สคีมารุ่นเดิม = edms 6.9 ดูของจำลองที่
 * install/testdb-module.php::testdbDmsLegacyState)
 *   dms_files.create_date     → created_at
 *   dms_download.last_update  → updated_at
 *   dms.department/cabinet    → แถวใน dms_meta
 *   number.type 'dms_format_no' → รูปแบบเลขที่เอกสาร
 *   config dms_user_permission → default_user_permissions
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$table_dms = $prefix.'_dms';
$table_dms_files = $prefix.'_dms_files';
$table_dms_meta = $prefix.'_dms_meta';
$table_dms_download = $prefix.'_dms_download';

// ---------------------------------------------------------
// เปลี่ยนชื่อคอลัมน์ ทำก่อนทุกอย่างในไฟล์นี้
//
// upgrade_core.php ตรวจคอลัมน์ที่ระบบนี้ไม่รู้จัก (ensureForeignColumnsDefault)
// "หลัง" โมดูลทุกตัว ถ้ายังเป็นชื่อเก่า create_date/last_update (datetime NOT NULL)
// ตอนนั้น จะขึ้นคำเตือนให้ผู้ใช้ไปแก้เอง ทั้งที่ตัวปรับรุ่นเปลี่ยนชื่อให้อยู่แล้ว
// (CHANGE เก็บค่าเดิมไว้ครบ)
// ---------------------------------------------------------
if ($db->tableExists($table_dms_files) && !$db->fieldExists($table_dms_files, 'created_at') && $db->fieldExists($table_dms_files, 'create_date')) {
    $db->query("ALTER TABLE `$table_dms_files` CHANGE `create_date` `created_at` DATETIME NOT NULL");
    $content[] = '<li class="correct">'.$table_dms_files.': เปลี่ยนชื่อ create_date → created_at</li>';
}
if ($db->tableExists($table_dms_download) && !$db->fieldExists($table_dms_download, 'updated_at') && $db->fieldExists($table_dms_download, 'last_update')) {
    $db->query("ALTER TABLE `$table_dms_download` CHANGE `last_update` `updated_at` DATETIME NULL DEFAULT NULL");
    $content[] = '<li class="correct">'.$table_dms_download.': เปลี่ยนชื่อ last_update → updated_at</li>';
}

// ---------------------------------------------------------
// สร้างตารางที่ยังไม่มี + แปลง engine/charset
// นิยามตารางอยู่ที่ modules/dms/install/database.sql ที่เดียว
// ---------------------------------------------------------
foreach ([$table_dms, $table_dms_files, $table_dms_meta, $table_dms_download] as $_t) {
    if (ensureTable($db, $prefix, $_t)) {
        $content[] = '<li class="correct">'.$_t.': สร้างตารางใหม่</li>';
    }
    // ⚠️ ต้องแปลงก่อนปรับคอลัมน์เสมอ — CONVERT TO CHARACTER SET เลื่อน
    // TEXT เป็น MEDIUMTEXT ถ้าแปลงทีหลังชนิดจะไม่ตรงกับที่ติดตั้งใหม่
    if (convertToInnoDB($db, $_t)) {
        $content[] = '<li class="correct">'.$_t.': แปลงเป็น InnoDB</li>';
    }
    if (convertToUtf8mb4($db, $_t)) {
        $content[] = '<li class="correct">'.$_t.': แปลงเป็น utf8mb4</li>';
    }
}

// ---------------------------------------------------------
// dms
// ---------------------------------------------------------
// ตัวติดตั้งรุ่นเก่าเติม PRIMARY KEY/AUTO_INCREMENT ด้วย ALTER ท้ายไฟล์
// ซึ่งตัวปรับรุ่นไม่เคยรัน ไซต์ที่ปรับรุ่นมาจึงอาจเพิ่มเอกสารไม่ได้
if (ensureAutoIncrement($db, $table_dms, 'id', 'int(11)')) {
    $content[] = '<li class="correct">'.$table_dms.': กำหนด id เป็น AUTO_INCREMENT</li>';
}
// ฐานรุ่นแรกเก็บแผนกและตู้เก็บเอกสารเป็นคอลัมน์ของ dms — ย้ายเข้า dms_meta
// (ข้ามแถวที่ย้ายไปแล้ว เพื่อให้รันซ้ำได้) แล้วค่อยลบคอลัมน์เดิม
foreach (['department', 'cabinet'] as $_type) {
    if ($db->fieldExists($table_dms, $_type)) {
        $db->query(
            "INSERT INTO `$table_dms_meta` (`dms_id`, `type`, `value`)"
            ." SELECT D.`id`, '$_type', D.`$_type` FROM `$table_dms` D"
            ." WHERE D.`$_type` IS NOT NULL AND D.`$_type` <> ''"
            ." AND NOT EXISTS (SELECT 1 FROM `$table_dms_meta` M WHERE M.`dms_id` = D.`id` AND M.`type` = '$_type')"
        );
        $db->query("ALTER TABLE `$table_dms` DROP COLUMN `$_type`");
        $content[] = '<li class="correct">'.$table_dms.': ย้าย '.$_type.' ไปเก็บใน dms_meta</li>';
    }
}
foreach ([
    'member_id' => ['int(11)', false, null, 'id'],
    'create_date' => ['date', false, null, 'member_id'],
    'document_no' => ['varchar(20)', false, null, 'create_date'],
    'detail' => ['text', false, null, 'document_no'],
    'topic' => ['varchar(255)', false, null, 'detail'],
    'url' => ['varchar(255)', false, '', 'topic']
] as $_col => $_def) {
    if (ensureColumn($db, $table_dms, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">'.$table_dms.': ปรับคอลัมน์ '.$_col.'</li>';
    }
}
if (ensureIndexes($db, $table_dms, [
    'document_no' => '`document_no`',
    'create_date' => '`create_date`',
    'member_id' => '`member_id`'
])) {
    $content[] = '<li class="correct">'.$table_dms.': ปรับดัชนี</li>';
}

// ---------------------------------------------------------
// dms_files (create_date → created_at ทำไปแล้วที่ต้นไฟล์)
// ---------------------------------------------------------
if (ensureAutoIncrement($db, $table_dms_files, 'id', 'int(11)')) {
    $content[] = '<li class="correct">'.$table_dms_files.': กำหนด id เป็น AUTO_INCREMENT</li>';
}
foreach ([
    'dms_id' => ['int(11)', false, null, 'id'],
    'topic' => ['varchar(150)', false, null, 'dms_id'],
    'name' => ['varchar(150)', false, null, 'topic'],
    'ext' => ['varchar(4)', false, null, 'name'],
    'size' => ['int(11)', false, null, 'ext'],
    'file' => ['varchar(50)', true, null, 'size'],
    'created_at' => ['datetime', false, null, 'file']
] as $_col => $_def) {
    if (ensureColumn($db, $table_dms_files, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">'.$table_dms_files.': ปรับคอลัมน์ '.$_col.'</li>';
    }
}
if (ensureIndexes($db, $table_dms_files, ['dms_id' => '`dms_id`'])) {
    $content[] = '<li class="correct">'.$table_dms_files.': ปรับดัชนี</li>';
}

// ---------------------------------------------------------
// dms_meta — ดัชนีรวม (dms_id, type) แทนดัชนีเดี่ยวสองตัวของรุ่นเก่า
// ---------------------------------------------------------
foreach ([
    'dms_id' => ['int(11)', false, null, ''],
    'type' => ['varchar(20)', false, null, 'dms_id'],
    'value' => ['text', false, null, 'type']
] as $_col => $_def) {
    if (ensureColumn($db, $table_dms_meta, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">'.$table_dms_meta.': ปรับคอลัมน์ '.$_col.'</li>';
    }
}
if (ensureIndexes($db, $table_dms_meta, ['idx_dms_type' => '`dms_id`, `type`'])) {
    $content[] = '<li class="correct">'.$table_dms_meta.': ปรับดัชนี</li>';
}
if (dropIndexes($db, $table_dms_meta, ['dms_id', 'type'])) {
    $content[] = '<li class="correct">'.$table_dms_meta.': ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}

// ---------------------------------------------------------
// dms_download — ดัชนีรวมที่ตรงกับ query จริง (last_update → updated_at ทำไปแล้วที่ต้นไฟล์)
// ---------------------------------------------------------
if (ensureAutoIncrement($db, $table_dms_download, 'id', 'int(11)')) {
    $content[] = '<li class="correct">'.$table_dms_download.': กำหนด id เป็น AUTO_INCREMENT</li>';
}
foreach ([
    'file_id' => ['int(11)', false, null, 'id'],
    'dms_id' => ['int(11)', false, null, 'file_id'],
    'member_id' => ['int(11)', false, null, 'dms_id'],
    'downloads' => ['int(11)', false, 0, 'member_id'],
    'updated_at' => ['datetime', true, null, 'downloads']
] as $_col => $_def) {
    if (ensureColumn($db, $table_dms_download, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">'.$table_dms_download.': ปรับคอลัมน์ '.$_col.'</li>';
    }
}
if (ensureIndexes($db, $table_dms_download, [
    'idx_download' => '`dms_id`, `file_id`, `member_id`',
    'member_id' => '`member_id`'
])) {
    $content[] = '<li class="correct">'.$table_dms_download.': ปรับดัชนี</li>';
}
if (dropIndexes($db, $table_dms_download, ['dms_id'])) {
    $content[] = '<li class="correct">'.$table_dms_download.': ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}

// ---------------------------------------------------------
// running number ของเลขที่เอกสาร
//
// ⚠️ ระบบเดิมเก็บ number.type เป็น "ชื่อค่ากำหนด" ('dms_format_no') แต่
// Index\Number\Model ของระบบนี้ใช้ "รูปแบบเลข" เป็น type ถ้าไม่แปลง
// เลขที่เอกสารจะเริ่มนับใหม่จาก 1 ทั้งที่ระบบเดิมออกเลขไปแล้ว
// ใช้เงื่อนไขเดียวกับ Index\Number\Model::get() เป๊ะ
// (ตาราง number เป็นของแกน upgrade_core ปรับรุ่นให้เสร็จก่อนมาถึงไฟล์นี้)
// ---------------------------------------------------------
$table_number = $prefix.'_number';
$_format = isset($config['dms_format_no']) ? (string) $config['dms_format_no'] : 'DOC%Y%M-%04d';
if ($_format === '') {
    $_format = isset($config['dms_prefix']) && $config['dms_prefix'] !== '' ? (string) $config['dms_prefix'] : '%04d';
}
$_numbers = $db->customQuery("SELECT `prefix`, `auto_increment` FROM `$table_number` WHERE `type` = 'dms_format_no'");
foreach ($_numbers as $_row) {
    $_exists = $db->customQuery(
        "SELECT `auto_increment` FROM `$table_number` WHERE `type` = :type AND `prefix` = :prefix",
        false,
        [':type' => $_format, ':prefix' => $_row->prefix]
    );
    if (empty($_exists)) {
        $db->customQuery(
            "UPDATE `$table_number` SET `type` = :type WHERE `type` = 'dms_format_no' AND `prefix` = :prefix",
            false,
            [':type' => $_format, ':prefix' => $_row->prefix]
        );
    } else {
        // มีแถวของรูปแบบใหม่อยู่แล้ว (ไซต์เคยออกเลขบนระบบนี้) — รวมเป็นแถวเดียว
        // โดยเก็บเลขที่สูงกว่าไว้ เลขที่ออกไปแล้วจะไม่ถูกออกซ้ำ
        $db->customQuery(
            "UPDATE `$table_number` SET `auto_increment` = GREATEST(`auto_increment`, :n) WHERE `type` = :type AND `prefix` = :prefix",
            false,
            [':n' => (int) $_row->auto_increment, ':type' => $_format, ':prefix' => $_row->prefix]
        );
        $db->customQuery(
            "DELETE FROM `$table_number` WHERE `type` = 'dms_format_no' AND `prefix` = :prefix",
            false,
            [':prefix' => $_row->prefix]
        );
        noteRowsMoved($table_number, $table_number, 1);
    }
}
if (!empty($_numbers)) {
    $content[] = '<li class="correct">'.$table_number.': แปลงเลขที่เอกสาร '.count($_numbers).' ชุดเป็นรูปแบบ '.htmlspecialchars($_format, ENT_QUOTES).'</li>';
}

// ---------------------------------------------------------
// ตู้เก็บเอกสารตั้งต้น — เฉพาะไซต์ที่ยังไม่มีตู้เลยสักตู้ ไซต์ที่ลบหรือเปลี่ยนชื่อ
// ตู้ไปแล้วต้องไม่ได้ตู้ตั้งต้นกลับมา (ชุดเดียวกับ INSERT ใน database.sql ของโมดูล)
// ---------------------------------------------------------
$table_category = $prefix.'_category';
if (!$db->first($table_category, ['type' => 'cabinet'])) {
    foreach (['1' => 'คำสั่ง', '2' => 'คู่มือ', '3' => 'ทรัพย์สิน'] as $_id => $_topic) {
        $db->insert($table_category, [
            'type' => 'cabinet',
            'category_id' => $_id,
            'language' => '',
            'topic' => $_topic,
            'is_active' => 1
        ]);
    }
    $content[] = '<li class="correct">'.$table_category.': เพิ่มตู้เก็บเอกสารตั้งต้น</li>';
}

// ---------------------------------------------------------
// สิทธิ์ตั้งต้นของสมาชิกใหม่ — ระบบเดิมเก็บสิทธิ์ของโมดูลไว้ที่ dms_user_permission
// ระบบนี้อ่านจาก default_user_permissions ที่เดียว (Index\Register\Model)
// install/upgrade2.php บันทึก $config ลง settings/config.php หลังโมดูลทุกตัวเสร็จ
// ---------------------------------------------------------
if (array_key_exists('dms_user_permission', $config)) {
    $_perms = isset($config['default_user_permissions']) && is_array($config['default_user_permissions']) ? $config['default_user_permissions'] : [];
    foreach ((array) $config['dms_user_permission'] as $_perm) {
        if (in_array($_perm, ['can_manage_dms', 'can_download_dms', 'can_upload_dms'], true) && !in_array($_perm, $_perms, true)) {
            $_perms[] = $_perm;
        }
    }
    $config['default_user_permissions'] = array_values($_perms);
    unset($config['dms_user_permission']);
    $content[] = '<li class="correct">config: ย้าย dms_user_permission ไปที่ default_user_permissions</li>';
}

// ---------------------------------------------------------
// ไฟล์เอกสารต้องเปิดผ่าน api/dms/download เท่านั้น (ตรวจสิทธิ์ + นับครั้ง)
// ระบบเดิมลิงก์ตรงเข้า datas/dms/ ได้ ใครรู้ที่อยู่ไฟล์ก็โหลดได้
// ---------------------------------------------------------
$_dms_dir = ROOT_PATH.'datas/dms/';
if (is_dir($_dms_dir) && !is_file($_dms_dir.'.htaccess')) {
    if (@file_put_contents($_dms_dir.'.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n")) {
        $content[] = '<li class="correct">datas/dms: ปิดการเข้าถึงไฟล์โดยตรง</li>';
    } else {
        $content[] = '<li class="warning">datas/dms: เขียนไฟล์ .htaccess ไม่ได้ กรุณาปรับสิทธิ์ของโฟลเดอร์ datas/dms/</li>';
    }
}
$content[] = '<li class="correct">dms อัปเกรดสำเร็จ</li>';
