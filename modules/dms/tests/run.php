<?php
/**
 * modules/dms/tests/run.php — ชุดทดสอบระบบจัดเก็บเอกสาร (dms)
 *
 * สร้างฐานทดสอบของตัวเองด้วยตัวติดตั้งจริง (install/cli-fresh.php) แล้วชี้ Kotchasan
 * ไปที่ฐานนั้นผ่าน APP_PATH ชั่วคราว เรียก Controller จริงด้วย token ของผู้ใช้แต่ละบทบาท
 * และพิสูจน์ตัวปรับรุ่นจากสคีมารุ่นแรกสุดของ edms (คอลัมน์ department/cabinet ใน dms)
 *
 * ไฟล์ที่อัปโหลดลงที่ datas/dms/9000xx/ ของโปรเจ็คจริง (DATA_FOLDER แก้ไม่ได้) และถูกลบทิ้งตอนจบ
 * settings/config.php ของโปรเจ็คถูกสำรองก่อนทดสอบหน้าตั้งค่าแล้วคืนให้ทุกครั้ง
 *
 * ใช้:  php modules/dms/tests/run.php [--db=<ชื่อฐานทดสอบ>] [--keep]
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$root = dirname(__DIR__, 3);
chdir($root);

$options = ['db' => 'nowtest_dms', 'keep' => false];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $match) && array_key_exists($match[1], $options)) {
        $options[$match[1]] = isset($match[2]) ? $match[2] : true;
    } else {
        fwrite(STDERR, "ไม่รู้จักตัวเลือก $arg\n");
        exit(1);
    }
}
$dbname = $options['db'];
$php = escapeshellarg(PHP_BINARY);

exec($php.' '.escapeshellarg($root.'/install/cli-fresh.php').' '.escapeshellarg($dbname).' app 2>&1', $output, $code);
if ($code !== 0) {
    fwrite(STDERR, "สร้างฐานทดสอบไม่ได้\n".implode("\n", $output)."\n");
    exit(1);
}

$work = sys_get_temp_dir().'/nowjs-dms-'.md5($root);
@mkdir($work.'/settings', 0700, true);
$database = include $root.'/settings/database.php';
$database['mysql']['dbname'] = $dbname;
file_put_contents($work.'/settings/database.php', "<?php\nreturn ".var_export($database, true).";\n");
// ค่ากำหนดของการทดสอบ — ทับ settings/config.php ของโปรเจ็คเฉพาะคีย์เหล่านี้
file_put_contents($work.'/settings/config.php', "<?php\nreturn ".var_export([
    'noreply_email' => '',
    'line_channel_access_token' => '',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    'demo_mode' => 0,
    'dms_format_no' => '%04d',
    'dms_prefix' => 'DOC%Y%M-',
    'dms_file_typies' => ['pdf', 'docx', 'zip'],
    'dms_upload_size' => 1024,
    'dms_download_action' => 0,
    'dms_upload_options' => 0,
    'dms_require_attach_file' => false
], true).";\n");
define('APP_PATH', $work.'/');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/api';
$_SERVER['SCRIPT_NAME'] = '/api.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'dms-tests';
session_save_path(sys_get_temp_dir());
@session_start();

include $root.'/load.php';
Kotchasan::createWebApplication('Gcms\Config');

$ok = 0;
$fail = 0;
$failed = [];

function t($label, $cond, $detail = '')
{
    global $ok, $fail, $failed;
    if ($cond) {
        ++$ok;
        echo "  [ok]   $label\n";
    } else {
        ++$fail;
        $failed[] = $label;
        echo "  [FAIL] $label".($detail === '' ? '' : "\n         $detail")."\n";
    }
}

function group($title)
{
    echo "\n== $title\n";
}

$db = \Kotchasan\DB::create();
$prefix = $database['mysql']['prefix'];
// อินสแตนซ์เดียวกับ self::$cfg ของทุกคลาส (singleton) — แก้ค่าแล้วมีผลทันที
$cfg = \Gcms\Config::create();
$pdoCfg = $database['mysql'];
$pdo = new PDO('mysql:host='.$pdoCfg['hostname'].';dbname='.$dbname.';charset=utf8mb4', $pdoCfg['username'], $pdoCfg['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Request ของผู้ใช้ $userId (0 = ไม่ได้เข้าระบบ) พร้อม CSRF token
 */
function req($method, $userId, array $query = [], array $body = [], array $files = [])
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $csrf = bin2hex(random_bytes(32));
    $_SESSION[$csrf] = ['times' => 0, 'expired' => time() + 3600, 'created' => time()];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;
    if ($userId > 0) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.\Index\Auth\Model::generateTokens($userId)['access_token'];
    } else {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
    $_GET = $query;
    $request = new \Kotchasan\Http\Request();

    return $request->withQueryParams($query)->withParsedBody($body)->withUploadedFiles($files);
}

function body($response)
{
    return json_decode((string) $response->getBody(), true);
}

function status($response)
{
    return $response->getStatusCode();
}

/**
 * ไฟล์อัปโหลดจำลอง
 */
function upload($name, $content)
{
    $tmp = tempnam(sys_get_temp_dir(), 'dms');
    file_put_contents($tmp, $content);

    return new \Kotchasan\Http\UploadedFile($tmp, strlen($content), UPLOAD_ERR_OK, $name, 'application/octet-stream');
}

function user($name, $status, array $permissions, $department = null)
{
    global $db;
    $id = $db->insert('user', [
        'username' => strtolower(str_replace(' ', '', $name)).'@example.com',
        'salt' => '',
        'password' => '',
        'status' => $status,
        'permission' => empty($permissions) ? '' : ','.implode(',', $permissions).',',
        'name' => $name,
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    foreach ((array) $department as $value) {
        $db->insert('user_meta', ['member_id' => $id, 'name' => 'department', 'value' => (string) $value]);
    }

    return $id;
}

/**
 * Controller ดาวน์โหลดที่เก็บข้อมูลไฟล์ไว้แทนการส่งออก (ส่งจริงจะ exit)
 */
class TestDownload extends \Dms\Download\Controller
{
    public $streamed = null;

    protected function stream(array $info)
    {
        $this->streamed = $info;

        return (new \Kotchasan\Http\Response())->withStatus(200);
    }
}

function download($method, $userId, array $query)
{
    $c = new TestDownload();
    $response = $c->$method(req('GET', $userId, $query));

    return [$response, $c->streamed];
}

function tableRows($class, $userId, array $query = [])
{
    $query += ['page' => 1, 'pageSize' => 100];
    $response = (new $class())->index(req('GET', $userId, $query));
    $b = body($response);

    return [status($response), $b['data']['data'] ?? [], $b];
}

$created = [];
$configFile = ROOT_PATH.'settings/config.php';
$configBackup = is_file($configFile) ? file_get_contents($configFile) : null;

try {
    // -------------------------------------------------------------------------
    group('ตัวติดตั้งใหม่');

    foreach (['dms', 'dms_files', 'dms_meta', 'dms_download'] as $table) {
        t("มีตาราง {$prefix}_$table", (bool) $pdo->query("SHOW TABLES LIKE '{$prefix}_$table'")->fetchColumn());
    }
    t('มีตู้เก็บเอกสารตั้งต้น 3 ตู้', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_category WHERE type='cabinet'")->fetchColumn() === 3);
    t('มีแผนกตั้งต้น 3 แผนก', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_category WHERE type='department'")->fetchColumn() === 3);
    t('สมาชิกใหม่ได้สิทธิ์ดาวน์โหลดเอกสารเป็นค่าตั้งต้น', in_array('can_download_dms', (array) (include ROOT_PATH.'install/settings/config.php')['default_user_permissions'], true));
    // ไฟล์ทดสอบเริ่มที่ datas/dms/900001 ไม่ชนกับไฟล์จริงของโปรเจ็ค
    $pdo->exec("ALTER TABLE {$prefix}_dms AUTO_INCREMENT = 900001");

    $admin = 1;
    $uploaderA = user('Uploader A', 2, ['can_upload_dms'], 1);
    $uploaderB = user('Uploader B', 2, ['can_upload_dms', 'can_download_dms'], 2);
    $reader1 = user('Reader One', 0, ['can_download_dms'], 1);
    $reader2 = user('Reader Two', 0, ['can_download_dms'], 2);
    $readerNoDept = user('Reader NoDept', 0, ['can_download_dms']);
    $manager = user('Manager', 2, ['can_manage_dms']);
    $nobody = user('Nobody', 0, []);

    // -------------------------------------------------------------------------
    group('สิทธิ์และเมนู');

    $perms = array_column(\Dms\Init\Controller::initPermission([]), 'value');
    t('ประกาศสิทธิ์ชื่อเดิม 3 ตัว', $perms === ['can_manage_dms', 'can_download_dms', 'can_upload_dms']);
    $menuOf = function ($userId) {
        return \Index\Menus\Controller::getMenus(\Index\Auth\Model::getUserById($userId));
    };
    $findMenu = function ($menus, $title) {
        foreach ($menus as $menu) {
            if (($menu['title'] ?? '') === $title) {
                return $menu;
            }
        }

        return null;
    };
    $m = $findMenu($menuOf($reader1), '{LNG_Document management system}');
    t('ผู้ดาวน์โหลดอย่างเดียวได้เมนูเดียวไปที่ /dms', $m && ($m['url'] ?? '') === '/dms' && empty($m['children']));
    $m = $findMenu($menuOf($uploaderA), '{LNG_Document management system}');
    t('ผู้อัปโหลดอย่างเดียวได้เมนูเดียวไปที่ /dms-setup', $m && ($m['url'] ?? '') === '/dms-setup');
    $m = $findMenu($menuOf($uploaderB), '{LNG_Document management system}');
    t('ผู้ที่ทำได้ทั้งสองอย่างได้เมนูย่อย 2 รายการ', $m && count($m['children'] ?? []) === 2);
    $m = $findMenu($menuOf($manager), '{LNG_Document management system}');
    t('ผู้จัดการตู้ที่ไม่มีเมนูตั้งค่า ได้เมนูตู้เก็บเอกสารใต้เมนูของโมดูล',
        $m && ($m['url'] ?? '') === '/dms-categories?type=cabinet');
    $settings = $findMenu($menuOf($admin), 'Settings');
    $dmsSettings = $settings ? $findMenu($settings['children'], '{LNG_Document management system}') : null;
    t('ผู้ดูแลระบบได้เมนูตั้งค่าโมดูลและตู้เก็บเอกสารใต้ "ตั้งค่า"',
        $dmsSettings && array_column($dmsSettings['children'], 'url') === ['/dms-settings', '/dms-categories?type=cabinet']);
    t('ผู้ไม่มีสิทธิ์ไม่เห็นเมนู', $findMenu($menuOf($nobody), '{LNG_Document management system}') === null);
    t('ผู้ดูแลระบบ (status 1) ผ่าน hasPermission', \Dms\Helper\Controller::canUpload(\Index\Auth\Model::getUserById($admin)));

    // -------------------------------------------------------------------------
    group('ฟอร์มเอกสาร — โหลด');

    $b = body((new \Dms\Write\Controller())->get(req('GET', $uploaderA, ['id' => 0])));
    t('เอกสารใหม่ตั้งแผนกเป็นแผนกของผู้อัปโหลด', ($b['data']['data']['department'] ?? null) === ['1']);
    t('เอกสารใหม่วันที่เป็นวันนี้', ($b['data']['data']['create_date'] ?? '') === date('Y-m-d'));
    t('ตัวเลือกแผนกครบทุกแผนกเมื่อไม่จำกัด', count($b['data']['options']['department'] ?? []) === 3);
    t('ตัวเลือก "ต้องการ" มีแนบไฟล์และ URL', array_column($b['data']['options']['want'] ?? [], 'value') === ['file', 'url']);
    t('ข้อความใต้ช่องไฟล์บอกชนิดและขนาด', strpos($b['data']['data']['file_comment'] ?? '', 'pdf, docx, zip') !== false);
    t('ผู้ไม่มีสิทธิ์อัปโหลดเปิดฟอร์มไม่ได้', status((new \Dms\Write\Controller())->get(req('GET', $reader1, ['id' => 0]))) === 403);

    // -------------------------------------------------------------------------
    group('ฟอร์มเอกสาร — ตรวจข้อมูล');

    $save = function ($userId, array $post, array $files = []) {
        return (new \Dms\Write\Controller())->save(req('POST', $userId, [], $post + [
            'id' => 0, 'document_no' => '', 'create_date' => date('Y-m-d'), 'topic' => 'เอกสาร', 'detail' => '',
            'want' => 'file', 'url' => '', 'department' => ['1'], 'cabinet' => '1', 'cabinet_text' => 'คำสั่ง'
        ], $files));
    };
    $r = $save($uploaderA, []);
    t('เอกสารใหม่ต้องแนบไฟล์', status($r) === 400 && isset(body($r)['errors']['file']));
    $r = $save($uploaderA, ['topic' => '', 'cabinet_text' => '', 'department' => []], ['file[0]' => upload('a.pdf', '%PDF')]);
    $e = body($r)['errors'] ?? [];
    t('หัวข้อ แผนก ตู้เอกสาร ต้องกรอก', isset($e['topic'], $e['department'], $e['cabinet']), json_encode($e));
    $r = $save($uploaderA, [], ['file[0]' => upload('a.exe', 'MZ')]);
    t('ชนิดไฟล์ไม่อนุญาต', isset(body($r)['errors']['file']));
    $r = $save($uploaderA, [], ['file[0]' => upload('big.pdf', str_repeat('x', 2048))]);
    t('ไฟล์ใหญ่เกินกำหนด', isset(body($r)['errors']['file']));
    $r = $save($uploaderA, ['want' => 'url', 'url' => 'ftp://example.com/x']);
    t('URL ต้องขึ้นต้นด้วย http/https', isset(body($r)['errors']['url']));
    $r = $save($uploaderA, ['want' => 'url', 'url' => '']);
    t('URL ต้องกรอก', isset(body($r)['errors']['url']));
    t('ยังไม่มีเอกสารถูกบันทึกจากคำขอที่ผิด', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms")->fetchColumn() === 0);

    // -------------------------------------------------------------------------
    group('ฟอร์มเอกสาร — บันทึก');

    $r = $save($uploaderA, ['topic' => 'คู่มือการใช้งาน', 'department' => ['1', '2', '9'], 'detail' => "บรรทัด 1\nบรรทัด 2"], [
        'file[0]' => upload('คู่มือ-ฉบับ;1.pdf', '%PDF-1.4 a'),
        'file[1]' => upload('แบบฟอร์ม.docx', 'PK docx')
    ]);
    $b = body($r);
    t('บันทึกเอกสารใหม่พร้อม 2 ไฟล์', status($r) === 200 && ($b['data']['actions'][0]['url'] ?? '') === '/dms-setup', json_encode($b, JSON_UNESCAPED_UNICODE));
    $doc1 = (int) $pdo->query("SELECT MAX(id) FROM {$prefix}_dms")->fetchColumn();
    $created[] = $doc1;
    $row = $pdo->query("SELECT * FROM {$prefix}_dms WHERE id=$doc1")->fetch(PDO::FETCH_ASSOC);
    $expectedNo = \Kotchasan\Number::printf('DOC%Y%M-', 0).'0001';
    t('ออกเลขที่เอกสารอัตโนมัติตามรูปแบบ', $row['document_no'] === $expectedNo, $row['document_no'].' != '.$expectedNo);
    t('เจ้าของเอกสารคือผู้อัปโหลด', (int) $row['member_id'] === $uploaderA);
    $meta = $pdo->query("SELECT type, value FROM {$prefix}_dms_meta WHERE dms_id=$doc1 ORDER BY type, value")->fetchAll(PDO::FETCH_NUM);
    t('เก็บเฉพาะแผนกที่มีจริง และตู้เอกสาร', $meta === [['cabinet', '1'], ['department', '1'], ['department', '2']], json_encode($meta));
    $files = $pdo->query("SELECT * FROM {$prefix}_dms_files WHERE dms_id=$doc1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    t('บันทึกไฟล์ 2 แถว', count($files) === 2);
    t('ชื่อไฟล์เดิมเก็บใน topic และ name แทนอักขระพิเศษด้วย _',
        $files[0]['topic'] === 'คู่มือ-ฉบับ;1' && $files[0]['name'] === 'คู่มือ_ฉบับ_1' && $files[0]['ext'] === 'pdf');
    t('ไฟล์อยู่ที่ datas/dms/<id>/ ด้วยชื่อสุ่ม', is_file(ROOT_PATH.DATA_FOLDER.$files[0]['file']) && strpos($files[0]['file'], 'dms/'.$doc1.'/') === 0);
    t('ปิดการเข้าถึง datas/dms โดยตรง', is_file(ROOT_PATH.DATA_FOLDER.'dms/.htaccess'));
    $file1 = (int) $files[0]['id'];
    $file2 = (int) $files[1]['id'];

    $r = $save($uploaderA, ['topic' => 'ตู้ใหม่', 'cabinet' => '', 'cabinet_text' => 'ตู้สัญญา', 'department' => ['1']], ['file[0]' => upload('x.zip', 'PK')]);
    $doc2 = (int) $pdo->query("SELECT MAX(id) FROM {$prefix}_dms")->fetchColumn();
    $created[] = $doc2;
    $cab = $pdo->query("SELECT category_id FROM {$prefix}_category WHERE type='cabinet' AND topic='ตู้สัญญา'")->fetchColumn();
    t('พิมพ์ชื่อตู้ใหม่แล้วระบบสร้างตู้ให้', $cab !== false && $pdo->query("SELECT value FROM {$prefix}_dms_meta WHERE dms_id=$doc2 AND type='cabinet'")->fetchColumn() === (string) $cab);
    $doc2No = $pdo->query("SELECT document_no FROM {$prefix}_dms WHERE id=$doc2")->fetchColumn();
    t('เลขที่เอกสารถัดไปเดินต่อ', $doc2No === \Kotchasan\Number::printf('DOC%Y%M-', 0).'0002', $doc2No);

    $r = $save($uploaderA, ['document_no' => $expectedNo], ['file[0]' => upload('dup.pdf', '%PDF')]);
    t('เลขที่เอกสารซ้ำไม่ได้', isset(body($r)['errors']['document_no']));

    $r = $save($uploaderB, ['topic' => 'ลิงก์ระเบียบ', 'want' => 'url', 'url' => 'https://example.com/rule', 'department' => ['2'], 'cabinet_text' => 'คู่มือ']);
    $doc3 = (int) $pdo->query("SELECT MAX(id) FROM {$prefix}_dms")->fetchColumn();
    $created[] = $doc3;
    t('บันทึกเอกสารแบบ URL ไม่ต้องแนบไฟล์', status($r) === 200 && $pdo->query("SELECT url FROM {$prefix}_dms WHERE id=$doc3")->fetchColumn() === 'https://example.com/rule');

    $r = $save($uploaderA, ['topic' => 'ทุกแผนก', 'department' => ['1', '2', '3']], ['file[0]' => upload('all.pdf', '%PDF')]);
    $doc4 = (int) $pdo->query("SELECT MAX(id) FROM {$prefix}_dms")->fetchColumn();
    $created[] = $doc4;
    $pdo->exec("UPDATE {$prefix}_dms SET create_date='2020-01-15' WHERE id=$doc4");

    $b = body((new \Dms\Write\Controller())->get(req('GET', $uploaderA, ['id' => $doc1])));
    t('เปิดแก้ไขได้ข้อมูลเดิมครบ', ($b['data']['data']['department'] ?? null) === ['1', '2'] && ($b['data']['data']['cabinet_text'] ?? '') === 'คำสั่ง'
        && ($b['data']['data']['want'] ?? '') === 'file');

    // -------------------------------------------------------------------------
    group('รายการเอกสารของผู้ดาวน์โหลด');

    list($code, $rows) = tableRows('\Dms\Documents\Controller', $reader1, ['sort' => 'create_date desc']);
    $docIds = array_values(array_unique(array_column($rows, 'dms_id')));
    sort($docIds);
    t('แผนก 1 เห็นเอกสารของแผนก 1 เท่านั้น (ไม่เห็นเอกสาร URL ของแผนก 2)', $code === 200 && $docIds === [$doc1, $doc2, $doc4], json_encode($docIds));
    t('เอกสารที่มี 2 ไฟล์แสดง 2 แถว', count(array_filter($rows, fn($row) => (int) $row['dms_id'] === $doc1)) === 2);
    $one = array_values(array_filter($rows, fn($row) => (int) $row['file_id'] === $file1))[0] ?? [];
    t('แถวมีชื่อไฟล์ ไอคอน แผนก ตู้เอกสาร', ($one['file_name'] ?? '') === 'คู่มือ-ฉบับ;1' && strpos($one['icon'] ?? '', 'images/ext/pdf.png') !== false
        && ($one['department'] ?? '') !== '' && ($one['cabinet'] ?? '') === 'คำสั่ง', json_encode($one, JSON_UNESCAPED_UNICODE));
    t('แผนกแสดงครบทุกแผนกของเอกสาร', count(explode(', ', $one['department'] ?? '')) === 2);
    t('รหัสแถวไม่ซ้ำ', count(array_unique(array_column($rows, 'id'))) === count($rows));
    t('ยังไม่ได้ดาวน์โหลด', (int) ($one['downloaded'] ?? 1) === 0);
    list(, $rows) = tableRows('\Dms\Documents\Controller', $readerNoDept);
    t('ผู้ไม่มีแผนกเห็นทุกเอกสาร', count(array_unique(array_column($rows, 'dms_id'))) === 4);
    list(, $rows) = tableRows('\Dms\Documents\Controller', $reader1, ['search' => 'แบบฟอร์ม']);
    t('ค้นหาจากชื่อไฟล์ได้', count($rows) === 1 && (int) $rows[0]['file_id'] === $file2);
    list(, $rows) = tableRows('\Dms\Documents\Controller', $readerNoDept, ['cabinet' => '1']);
    $ids = array_values(array_unique(array_map('intval', array_column($rows, 'dms_id'))));
    sort($ids);
    t('กรองตามตู้เอกสาร', $ids === [$doc1, $doc4], json_encode($ids));
    list(, $rows) = tableRows('\Dms\Documents\Controller', $readerNoDept, ['from' => '2020-01-01', 'to' => '2020-12-31']);
    t('กรองตามช่วงวันที่', array_values(array_unique(array_column($rows, 'dms_id'))) === [$doc4]);
    list($code, , $b) = tableRows('\Dms\Documents\Controller', $readerNoDept);
    t('ส่งตัวเลือกตู้เอกสารให้ตัวกรอง', count($b['data']['filters']['cabinet'] ?? []) >= 4);
    list($code) = tableRows('\Dms\Documents\Controller', $uploaderA);
    t('ผู้ไม่มีสิทธิ์ดาวน์โหลดเปิดรายการไม่ได้', $code === 403);

    // -------------------------------------------------------------------------
    group('ดาวน์โหลดและนับครั้ง');

    list($r, $info) = download('index', $reader1, ['id' => $file1]);
    t('ดาวน์โหลดไฟล์ได้', $info !== null && $info['name'] === 'คู่มือ_ฉบับ_1.pdf' && $info['inline'] === false && $info['mime'] === 'application/pdf');
    download('index', $reader1, ['id' => $file1]);
    $count = (int) $pdo->query("SELECT downloads FROM {$prefix}_dms_download WHERE file_id=$file1 AND member_id=$reader1")->fetchColumn();
    t('นับ 2 ครั้งในแถวเดียว', $count === 2);
    list(, $rows) = tableRows('\Dms\Documents\Controller', $reader1);
    $flags = [];
    foreach ($rows as $row) {
        $flags[(int) $row['file_id']] = (int) $row['downloaded'];
    }
    t('รายการแสดงว่าดาวน์โหลดไฟล์นี้แล้ว ไฟล์อื่นยัง', ($flags[$file1] ?? 0) === 1 && ($flags[$file2] ?? 1) === 0);
    list(, $rows) = tableRows('\Dms\Documents\Controller', $readerNoDept);
    $other = array_values(array_filter($rows, fn($row) => (int) $row['file_id'] === $file1))[0] ?? [];
    t('การดาวน์โหลดของคนหนึ่งไม่ทำให้อีกคนขึ้นว่าดาวน์โหลดแล้ว', (int) ($other['downloaded'] ?? 1) === 0);
    $cfg->dms_download_action = 1;
    list(, $info) = download('index', $reader1, ['id' => $file1]);
    t('ตั้งค่า "เปิดไฟล์" แล้ว pdf เปิดในเบราว์เซอร์', $info['inline'] === true);
    list(, $info) = download('index', $readerNoDept, ['id' => $file2]);
    t('ไฟล์ชนิดที่ไม่รู้จักยังเป็นการดาวน์โหลด', $info['inline'] === false);
    $cfg->dms_download_action = 0;
    list($r, $info) = download('index', $reader2, ['id' => (int) $pdo->query("SELECT id FROM {$prefix}_dms_files WHERE dms_id=$doc2")->fetchColumn()]);
    t('ไฟล์ของแผนกอื่นดาวน์โหลดไม่ได้ (พาไปหน้า 404)', $info === null && strpos($r->getHeaderLine('Location'), '404') !== false);
    list($r, $info) = download('index', $uploaderA, ['id' => $file1]);
    t('ไม่มีสิทธิ์ดาวน์โหลด (พาไปหน้า 403)', $info === null && strpos($r->getHeaderLine('Location'), '403') !== false);
    list($r, $info) = download('index', 0, ['id' => $file1]);
    t('ไม่ได้เข้าระบบ (พาไปหน้าเข้าระบบ)', $info === null && strpos($r->getHeaderLine('Location'), 'login') !== false);
    list($r) = download('url', $reader2, ['id' => $doc3]);
    t('เปิดลิงก์ของเอกสาร URL', status($r) === 302 && $r->getHeaderLine('Location') === 'https://example.com/rule');
    t('นับการเปิดลิงก์ด้วย file_id = 0', (int) $pdo->query("SELECT downloads FROM {$prefix}_dms_download WHERE dms_id=$doc3 AND file_id=0 AND member_id=$reader2")->fetchColumn() === 1);
    list($r) = download('url', $reader1, ['id' => $doc3]);
    t('ลิงก์ของแผนกอื่นเปิดไม่ได้', strpos($r->getHeaderLine('Location'), '404') !== false);
    $before = (int) $pdo->query("SELECT COALESCE(SUM(downloads),0) FROM {$prefix}_dms_download")->fetchColumn();
    list(, $info) = download('manage', $uploaderA, ['id' => $file1]);
    $after = (int) $pdo->query("SELECT COALESCE(SUM(downloads),0) FROM {$prefix}_dms_download")->fetchColumn();
    t('ผู้อัปโหลดดาวน์โหลดจากหน้าไฟล์ได้โดยไม่นับครั้ง', $info !== null && $before === $after);
    list(, $info) = download('manage', $reader1, ['id' => $file1]);
    t('ผู้ดาวน์โหลดใช้ทางของผู้อัปโหลดไม่ได้', $info === null);
    $fake = (object) ['file' => '../settings/config.php', 'name' => 'x', 'ext' => 'php'];
    t('ไฟล์นอก datas/dms ส่งออกไม่ได้', \Dms\Download\Model::prepare($fake) === null);

    // -------------------------------------------------------------------------
    group('รายละเอียดเอกสาร (modal)');

    $b = body((new \Dms\Detail\Controller())->get(req('GET', $reader1, ['id' => $doc1])));
    $d = $b['data']['data'] ?? [];
    t('เปิด modal พร้อมแม่แบบ', ($b['data']['actions'][0]['template'] ?? '') === 'dms/detail.html');
    t('รายละเอียดครบ', ($d['topic'] ?? '') === 'คู่มือการใช้งาน' && ($d['detail'] ?? '') === "บรรทัด 1\nบรรทัด 2" && count($d['files'] ?? []) === 2);
    t('ไฟล์ที่ดาวน์โหลดแล้วมีเครื่องหมาย', array_column($d['files'] ?? [], 'downloaded') === [1, 0]);
    t('เอกสารของแผนกอื่นเปิดไม่ได้', status((new \Dms\Detail\Controller())->get(req('GET', $reader1, ['id' => $doc3]))) === 404);

    // -------------------------------------------------------------------------
    group('รายการเอกสารของผู้อัปโหลด');

    list($code, $rows, $b) = tableRows('\Dms\Setup\Controller', $uploaderA, ['sort' => 'document_no asc']);
    t('เห็นเอกสารทั้งหมด 4 ฉบับ (ไม่จำกัดแผนก)', $code === 200 && count($rows) === 4);
    $first = $rows[0] ?? [];
    t('เรียงตามเลขที่เอกสาร', ($first['document_no'] ?? '') === $expectedNo);
    t('แสดงแผนกครบ', count(explode(', ', $first['department'] ?? '')) === 2 && ($first['cabinet'] ?? '') === 'คำสั่ง');
    t('แก้ไขได้ทุกฉบับเมื่อไม่จำกัด', array_sum(array_column($rows, 'can_edit')) === 4);
    t('ส่งตัวเลือกแผนกและตู้ให้ตัวกรอง', count($b['data']['filters']['department'] ?? []) === 3 && count($b['data']['filters']['cabinet'] ?? []) >= 4);
    list(, $rows) = tableRows('\Dms\Setup\Controller', $uploaderA, ['department' => '3']);
    t('กรองตามแผนก (แสดงทุกแผนกของเอกสารนั้น)', count($rows) === 1 && count(explode(', ', $rows[0]['department'])) === 3);
    list($code) = tableRows('\Dms\Setup\Controller', $reader1);
    t('ผู้ไม่มีสิทธิ์อัปโหลดเปิดไม่ได้', $code === 403);

    // -------------------------------------------------------------------------
    group('อัปโหลดได้เฉพาะแผนกของตัวเอง (dms_upload_options = 1)');

    $cfg->dms_upload_options = 1;
    list(, $rows, $b) = tableRows('\Dms\Setup\Controller', $uploaderB);
    $ids = array_column($rows, 'id');
    sort($ids);
    t('เห็นเฉพาะเอกสารที่ส่งถึงแผนกของตัวเอง', $ids === [$doc1, $doc3, $doc4], json_encode($ids));
    t('ตัวกรองแผนกมีแค่แผนกของตัวเอง', array_map('strval', array_column($b['data']['filters']['department'] ?? [], 'value')) === ['2'],
        json_encode($b['data']['filters']['department'] ?? null, JSON_UNESCAPED_UNICODE));
    $editable = [];
    foreach ($rows as $row) {
        $editable[(int) $row['id']] = (int) $row['can_edit'];
    }
    t('แก้ไขได้เฉพาะเอกสารของตัวเอง', $editable == [$doc1 => 0, $doc3 => 1, $doc4 => 0], json_encode($editable));
    t('เปิดฟอร์มเอกสารของคนอื่นไม่ได้', status((new \Dms\Write\Controller())->get(req('GET', $uploaderB, ['id' => $doc1]))) === 403);
    $r = $save($uploaderB, ['id' => $doc1, 'topic' => 'แก้ของคนอื่น']);
    t('บันทึกทับเอกสารของคนอื่นไม่ได้', status($r) === 403);
    $r = $save($uploaderB, ['topic' => 'ส่งข้ามแผนก', 'want' => 'url', 'url' => 'https://example.com/x', 'department' => ['1']]);
    t('ส่งเอกสารถึงแผนกอื่นไม่ได้', isset(body($r)['errors']['department']));
    $r = (new \Dms\Setup\Controller())->action(req('POST', $uploaderB, [], ['action' => 'delete', 'ids' => [$doc1]]));
    t('ลบเอกสารของคนอื่นไม่ได้ (ระบบเดิมลบได้)', status($r) === 400 && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms WHERE id=$doc1")->fetchColumn() === 1);
    list($code) = tableRows('\Dms\Files\Controller', $uploaderB, ['dms_id' => $doc1]);
    t('ดูไฟล์ของเอกสารคนอื่นไม่ได้ (ระบบเดิมดูได้)', $code === 403);
    list($code) = tableRows('\Dms\Report\Controller', $uploaderB, ['file_id' => $file1]);
    t('ดูประวัติดาวน์โหลดของเอกสารคนอื่นไม่ได้', $code === 403);
    list(, $rows) = tableRows('\Dms\Setup\Controller', $admin);
    t('ผู้ดูแลระบบไม่ถูกจำกัด', count($rows) === 4 && array_sum(array_column($rows, 'can_edit')) === 4);
    $cfg->dms_upload_options = 0;

    // -------------------------------------------------------------------------
    group('ไฟล์ของเอกสาร และประวัติการดาวน์โหลด');

    list($code, $rows, $b) = tableRows('\Dms\Files\Controller', $uploaderA, ['dms_id' => $doc1]);
    t('รายการไฟล์ของเอกสาร', $code === 200 && count($rows) === 2 && ($b['data']['options']['document']['document_no'] ?? '') === $expectedNo);
    t('ไฟล์มีไอคอนและขนาด', strpos($rows[0]['icon'] ?? '', 'images/ext/') !== false && (int) $rows[0]['size'] > 0);
    list($code, $rows, $b) = tableRows('\Dms\Report\Controller', $uploaderA, ['file_id' => $file1]);
    t('ประวัติดาวน์โหลดของไฟล์', $code === 200 && count($rows) === 1 && (int) $rows[0]['downloads'] === 3 && $rows[0]['name'] === 'Reader One');
    t('ส่งชื่อสถานะสมาชิกให้คอลัมน์ lookup', !empty($b['data']['options']['status']));
    // บั๊กของระบบเดิม: ลบ dms_download ด้วย id ของแถวแทน file_id — ทำให้แถวของเอกสารอื่น
    // ที่ id บังเอิญเท่ากับ id ของไฟล์ที่ลบหายไป จำลองให้ชนกันจริงแล้วต้องไม่หาย
    download('index', $reader1, ['id' => $file2]);
    $maxId = (int) $pdo->query("SELECT MAX(id) FROM {$prefix}_dms_download")->fetchColumn();
    $pdo->exec("UPDATE {$prefix}_dms_download SET id = ".($maxId + 100)." WHERE id = $file2");
    $db->insert('dms_download', ['dms_id' => $doc4, 'file_id' => 999, 'member_id' => $reader2, 'downloads' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
    $pdo->exec("UPDATE {$prefix}_dms_download SET id = $file2 WHERE file_id = 999");
    $otherRows = (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_download WHERE dms_id <> $doc1")->fetchColumn();
    $path2 = ROOT_PATH.DATA_FOLDER.$pdo->query("SELECT file FROM {$prefix}_dms_files WHERE id=$file2")->fetchColumn();
    $r = (new \Dms\Files\Controller())->action(req('POST', $uploaderA, [], ['action' => 'delete', 'ids' => [$file2]]));
    t('ลบไฟล์ที่เลือก', status($r) === 200 && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_files WHERE id=$file2")->fetchColumn() === 0);
    t('ไฟล์บนดิสก์ถูกลบ', !is_file($path2));
    t('ประวัติดาวน์โหลดของไฟล์ที่ลบถูกลบ', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_download WHERE file_id=$file2")->fetchColumn() === 0);
    t('ประวัติของไฟล์อื่น/เอกสารอื่นยังอยู่ครบ', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_download WHERE dms_id <> $doc1")->fetchColumn() === $otherRows
        && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_download WHERE file_id=$file1")->fetchColumn() === 1);

    // -------------------------------------------------------------------------
    group('แก้ไขเอกสาร');

    $r = $save($uploaderA, ['id' => $doc1, 'topic' => 'คู่มือ (แก้ไข)', 'document_no' => $expectedNo], ['file[0]' => upload('เพิ่ม.pdf', '%PDF')]);
    t('แก้ไขพร้อมแนบไฟล์เพิ่ม', status($r) === 200 && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_files WHERE dms_id=$doc1")->fetchColumn() === 2);
    t('แก้ไขโดยคงเลขที่เดิมได้', $pdo->query("SELECT document_no FROM {$prefix}_dms WHERE id=$doc1")->fetchColumn() === $expectedNo);
    $r = $save($uploaderA, ['id' => $doc1, 'topic' => 'ไม่แนบไฟล์เพิ่ม', 'document_no' => $expectedNo]);
    t('แก้ไขโดยไม่แนบไฟล์เพิ่มได้', status($r) === 200);
    $r = $save($uploaderA, ['id' => $doc1, 'topic' => 'เปลี่ยนเป็นลิงก์', 'document_no' => $expectedNo, 'want' => 'url', 'url' => 'https://example.com/new']);
    t('เปลี่ยนเป็นเอกสารแบบ URL แล้วไฟล์เดิมถูกลบ', status($r) === 200
        && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_files WHERE dms_id=$doc1")->fetchColumn() === 0
        && !is_dir(ROOT_PATH.DATA_FOLDER.'dms/'.$doc1));
    t('ประวัติการดาวน์โหลดไฟล์เดิมถูกลบด้วย', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_download WHERE dms_id=$doc1 AND file_id>0")->fetchColumn() === 0);

    // -------------------------------------------------------------------------
    group('ลบเอกสาร');

    $before = (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_meta WHERE dms_id=$doc2")->fetchColumn();
    $r = (new \Dms\Setup\Controller())->action(req('POST', $uploaderA, [], ['action' => 'delete', 'ids' => [$doc2]]));
    t('ลบเอกสาร', status($r) === 200 && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms WHERE id=$doc2")->fetchColumn() === 0);
    t('ลบไฟล์ หมวดหมู่ และโฟลเดอร์ของเอกสาร', $before > 0
        && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_meta WHERE dms_id=$doc2")->fetchColumn() === 0
        && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_dms_files WHERE dms_id=$doc2")->fetchColumn() === 0
        && !is_dir(ROOT_PATH.DATA_FOLDER.'dms/'.$doc2));
    t('บันทึก log การลบ', (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_logs WHERE module='dms' AND action='Delete'")->fetchColumn() >= 2);

    // -------------------------------------------------------------------------
    group('การ์ดหน้าแรก');

    $card = \Dms\Init\Controller::initDashboard([], null, \Index\Auth\Model::getUserById($reader2))[0] ?? [];
    t('การ์ดลิงก์ไปรายการเอกสาร 30 วัน', strpos($card['url'] ?? '', '/dms?from=') === 0);
    $countNew = function ($userId) {
        return \Dms\Dashboard\Model::countNew(\Index\Auth\Model::getUserById($userId), date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    };
    // ใน 30 วันเหลือ doc1 (เปลี่ยนเป็น URL แผนก 1 ตอนแก้ไข) และ doc3 (URL แผนก 2) — reader2 เปิด doc3 แล้ว
    t('นับเป็นจำนวนเอกสาร ไม่ใช่ไฟล์ x แผนก', $countNew($readerNoDept) === 2, (string) $countNew($readerNoDept));
    t('นับเฉพาะเอกสารของแผนกตัวเอง', $countNew($reader1) === 1, (string) $countNew($reader1));
    t('ไม่นับเอกสารที่เปิดแล้ว', $countNew($reader2) === 0, (string) $countNew($reader2));
    // เอกสาร 1 ฉบับที่มีหลายไฟล์และส่งถึง 3 แผนก ต้องนับ 1 (ระบบเดิมนับ ไฟล์ x แผนก)
    $multi = $db->insert('dms', ['member_id' => $uploaderA, 'create_date' => date('Y-m-d'), 'document_no' => 'MULTI', 'detail' => '', 'topic' => 'หลายแผนก', 'url' => '']);
    $created[] = $multi;
    foreach (['1', '2', '3'] as $dep) {
        $db->insert('dms_meta', ['dms_id' => $multi, 'type' => 'department', 'value' => $dep]);
    }
    foreach (['a', 'b'] as $name) {
        $db->insert('dms_files', ['dms_id' => $multi, 'topic' => $name, 'name' => $name, 'ext' => 'pdf', 'size' => 1, 'file' => 'dms/'.$multi.'/'.$name.'.pdf', 'created_at' => date('Y-m-d H:i:s')]);
    }
    t('เอกสารหลายไฟล์หลายแผนกนับเป็น 1', $countNew($readerNoDept) === 3, (string) $countNew($readerNoDept));
    t('ผู้ไม่มีสิทธิ์ดาวน์โหลดไม่ได้การ์ด', \Dms\Init\Controller::initDashboard([], null, \Index\Auth\Model::getUserById($uploaderA)) === []);

    // -------------------------------------------------------------------------
    group('ตู้เก็บเอกสาร');

    $b = body((new \Dms\Categories\Controller())->get(req('GET', $manager, ['type' => 'cabinet'])));
    t('ผู้จัดการตู้ (ไม่มี can_config) เปิดหน้าตู้ได้', count($b['data']['data']['options']['data'] ?? []) >= 4);
    t('ผู้ไม่มีสิทธิ์เปิดหน้าตู้ไม่ได้', status((new \Dms\Categories\Controller())->get(req('GET', $reader1, ['type' => 'cabinet']))) === 403);
    t('แก้หมวดหมู่แผนกจากหน้านี้ไม่ได้', status((new \Dms\Categories\Controller())->get(req('GET', $manager, ['type' => 'department']))) === 404);
    $lng = \Index\Language\Model::getLanguages()[0];
    $r = (new \Dms\Categories\Controller())->save(req('POST', $manager, [], ['type' => 'cabinet', 'id' => ['1', '2'], $lng => ['คำสั่ง', 'คู่มือใหม่']]));
    $topics = $pdo->query("SELECT topic FROM {$prefix}_category WHERE type='cabinet' ORDER BY category_id")->fetchAll(PDO::FETCH_COLUMN);
    t('บันทึกตู้เก็บเอกสาร', status($r) === 200 && $topics === ['คำสั่ง', 'คู่มือใหม่'], json_encode($topics, JSON_UNESCAPED_UNICODE));
    $r = (new \Dms\Categories\Controller())->save(req('POST', $manager, [], ['type' => 'cabinet', 'id' => ['1'], $lng => ['']]));
    t('ส่งตู้ว่างทั้งหมดไม่ทำให้ตู้หาย', status($r) === 400 && (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}_category WHERE type='cabinet'")->fetchColumn() === 2);

    // -------------------------------------------------------------------------
    group('ตั้งค่าโมดูล');

    $b = body((new \Dms\Settings\Controller())->get(req('GET', $admin)));
    $d = $b['data']['data'] ?? [];
    t('โหลดค่าตั้งค่า', ($d['dms_prefix'] ?? '') === 'DOC%Y%M-' && ($d['dms_file_typies'] ?? '') === 'pdf,docx,zip');
    t('ตัวเลือกขนาดไฟล์มีค่าปัจจุบันอยู่ด้วย', in_array('1024', array_column($b['data']['options']['dms_upload_size'] ?? [], 'value'), true));
    t('ผู้ไม่มี can_config เปิดไม่ได้', status((new \Dms\Settings\Controller())->get(req('GET', $manager))) === 403);
    $r = (new \Dms\Settings\Controller())->save(req('POST', $admin, [], ['dms_file_typies' => 'pdf,abcdef']));
    t('ชนิดไฟล์ยาวเกิน 4 ตัวไม่ได้', isset(body($r)['errors']['dms_file_typies']));
    $r = (new \Dms\Settings\Controller())->save(req('POST', $admin, [], [
        'dms_prefix' => 'EDMS%Y-', 'dms_format_no' => '%05d', 'dms_file_typies' => 'PDF, xlsx,pdf', 'dms_upload_size' => 2097152,
        'dms_download_action' => 1, 'dms_upload_options' => 1, 'dms_require_attach_file' => 1,
        'dms_user_permission' => ['can_upload_dms', 'can_config']
    ]));
    $saved = include ROOT_PATH.'settings/config.php';
    $keys = ['dms_prefix', 'dms_format_no', 'dms_file_typies', 'dms_upload_size', 'dms_download_action', 'dms_upload_options', 'dms_require_attach_file'];
    $got = array_intersect_key($saved, array_flip($keys));
    t('บันทึกค่าตั้งค่า', status($r) === 200 && $got == [
        'dms_prefix' => 'EDMS%Y-', 'dms_format_no' => '%05d', 'dms_file_typies' => ['pdf', 'xlsx'], 'dms_upload_size' => 2097152,
        'dms_download_action' => 1, 'dms_upload_options' => 1, 'dms_require_attach_file' => true
    ], status($r).' '.json_encode($got));
    t('สิทธิ์ตั้งต้นแก้เฉพาะสิทธิ์ของโมดูล (รับเฉพาะสิทธิ์ dms)', in_array('can_upload_dms', $saved['default_user_permissions'], true)
        && !in_array('can_config', $saved['default_user_permissions'], true));
} catch (\Throwable $e) {
    t('ไม่มีข้อผิดพลาดระหว่างทดสอบ', false, get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
} finally {
    if ($configBackup !== null) {
        file_put_contents($configFile, $configBackup);
    }
    foreach ($created as $id) {
        \Dms\Helper\Controller::removeDocumentDir($id);
    }
}

// -----------------------------------------------------------------------------
group('ตัวปรับรุ่น — สคีมารุ่นแรกสุดของ edms (department/cabinet เป็นคอลัมน์ของ dms)');

$legacy = $dbname.'_legacy';
$legacyConfig = $work.'/legacy-config.php';
exec($php.' '.escapeshellarg($root.'/install/cli-fresh.php').' '.escapeshellarg($legacy).' app --config='.escapeshellarg($legacyConfig).' 2>&1', $output, $code);
t('สร้างฐานตั้งต้นได้', $code === 0);
$lp = new PDO('mysql:host='.$pdoCfg['hostname'].';dbname='.$legacy.';charset=utf8mb4', $pdoCfg['username'], $pdoCfg['password']);
$lp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['dms', 'dms_files', 'dms_meta', 'dms_download'] as $table) {
    $lp->exec("DROP TABLE IF EXISTS app_$table");
}
$lp->exec("CREATE TABLE app_dms (id int(11) NOT NULL AUTO_INCREMENT, member_id int(11) NOT NULL, create_date date NOT NULL,
    document_no varchar(20) NOT NULL, detail text NOT NULL, topic varchar(255) NOT NULL, department varchar(10) DEFAULT NULL,
    cabinet varchar(10) DEFAULT NULL, PRIMARY KEY (id)) ENGINE=MyISAM DEFAULT CHARSET=utf8");
$lp->exec("CREATE TABLE app_dms_files (id int(11) NOT NULL AUTO_INCREMENT, dms_id int(11) NOT NULL, topic varchar(150) NOT NULL,
    name varchar(150) NOT NULL, ext varchar(4) NOT NULL, size int(11) NOT NULL, file varchar(50) DEFAULT NULL, create_date datetime NOT NULL,
    PRIMARY KEY (id)) ENGINE=MyISAM DEFAULT CHARSET=utf8");
$lp->exec("CREATE TABLE app_dms_download (id int(11) NOT NULL AUTO_INCREMENT, file_id int(11) NOT NULL, dms_id int(11) NOT NULL,
    member_id int(11) NOT NULL, downloads int(11) NOT NULL, last_update datetime NOT NULL, PRIMARY KEY (id)) ENGINE=MyISAM DEFAULT CHARSET=utf8");
$lp->exec("INSERT INTO app_dms VALUES (1, 1, '2019-05-01', 'DOC6205-0001', 'เก่ามาก', 'เอกสารรุ่นแรก', '2', '3'),
    (2, 1, '2019-05-02', 'DOC6205-0002', '', 'ไม่มีหมวด', NULL, '')");
$lp->exec("INSERT INTO app_dms_files VALUES (1, 1, 'old', 'old', 'pdf', 10, 'dms/1/old.pdf', '2019-05-01 10:00:00')");
$lp->exec("INSERT INTO app_dms_download VALUES (1, 1, 1, 1, 4, '2019-05-03 10:00:00')");
$lp->exec("INSERT INTO app_number (type, prefix, auto_increment) VALUES ('dms_format_no', 'DOC6205-', 2)");
$cfgLegacy = include $legacyConfig;
$cfgLegacy['dms_format_no'] = '%04d';
$cfgLegacy['dms_prefix'] = 'DOC%Y%M-';
$cfgLegacy['dms_user_permission'] = ['can_download_dms', 'can_upload_dms', 'not_a_dms_permission'];
$cfgLegacy['default_user_permissions'] = ['can_view_usage_history'];
file_put_contents($legacyConfig, "<?php\nreturn ".var_export($cfgLegacy, true).";\n");
foreach ([1, 2] as $round) {
    exec($php.' '.escapeshellarg($root.'/install/cli-upgrade.php').' admin@localhost admin --confirm-backup --db='.escapeshellarg($legacy)
        .' --prefix=app --config='.escapeshellarg($legacyConfig).' 2>&1', $out, $code);
    t("ปรับรุ่นผ่าน (รอบที่ $round)", $code === 0, implode("\n", array_slice($out, -5)));
    $out = [];
}
$cols = $lp->query("SHOW COLUMNS FROM app_dms")->fetchAll(PDO::FETCH_COLUMN);
t('ย้ายคอลัมน์ department/cabinet ออกจาก dms และเพิ่ม url', !in_array('department', $cols, true) && !in_array('cabinet', $cols, true) && in_array('url', $cols, true));
$meta = $lp->query("SELECT CONCAT(dms_id, ':', type, ':', value) FROM app_dms_meta ORDER BY dms_id, type")->fetchAll(PDO::FETCH_COLUMN);
t('แผนก/ตู้เอกสารย้ายเข้า dms_meta (ไม่ย้ายค่าว่าง) และไม่ซ้ำเมื่อรันสองรอบ', $meta === ['1:cabinet:3', '1:department:2'], json_encode($meta));
t('dms_files.create_date → created_at', $lp->query("SELECT created_at FROM app_dms_files WHERE id=1")->fetchColumn() === '2019-05-01 10:00:00');
t('dms_download.last_update → updated_at', $lp->query("SELECT updated_at FROM app_dms_download WHERE id=1")->fetchColumn() === '2019-05-03 10:00:00');
t('ตาราง dms เป็น InnoDB/utf8mb4', $lp->query("SELECT CONCAT(ENGINE, ' ', TABLE_COLLATION) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$legacy' AND TABLE_NAME='app_dms'")->fetchColumn() === 'InnoDB utf8mb4_general_ci');
t('เลขที่เอกสารเดินต่อจากระบบเดิม', (int) $lp->query("SELECT auto_increment FROM app_number WHERE type='%04d' AND prefix='DOC6205-'")->fetchColumn() === 2
    && (int) $lp->query("SELECT COUNT(*) FROM app_number WHERE type='dms_format_no'")->fetchColumn() === 0);
$cfgAfter = include $legacyConfig;
t('dms_user_permission ย้ายไป default_user_permissions (เฉพาะสิทธิ์ dms) และเก็บสิทธิ์เดิมไว้',
    !isset($cfgAfter['dms_user_permission']) && $cfgAfter['default_user_permissions'] === ['can_view_usage_history', 'can_download_dms', 'can_upload_dms'],
    json_encode($cfgAfter['default_user_permissions'] ?? null));
$fresh = [];
foreach (['dms', 'dms_files', 'dms_meta', 'dms_download'] as $table) {
    $a = $lp->query("SHOW CREATE TABLE app_$table")->fetch(PDO::FETCH_NUM)[1];
    $b = $pdo->query("SHOW CREATE TABLE {$prefix}_$table")->fetch(PDO::FETCH_NUM)[1];
    $strip = fn($sql) => preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
    t("สคีมา $table หลังปรับรุ่นตรงกับติดตั้งใหม่", $strip($a) === $strip($b), $strip($a)."\n---\n".$strip($b));
}

if (!$options['keep']) {
    $pdo->exec("DROP DATABASE `$dbname`");
    $lp->exec("DROP DATABASE `$legacy`");
}

echo "\n".str_repeat('=', 70)."\n";
echo "ผ่าน $ok · ไม่ผ่าน $fail\n";
foreach ($failed as $label) {
    echo "  - $label\n";
}
exit($fail === 0 ? 0 : 1);
