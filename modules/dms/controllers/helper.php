<?php
/**
 * @filesource modules/dms/controllers/helper.php
 *
 * กติกาเรื่องสิทธิ์ของระบบจัดเก็บเอกสารรวมไว้ที่เดียว ทุก endpoint เรียกจากที่นี่
 * เพื่อให้หน้ารายการ ปุ่ม และ endpoint ตัดสินแบบเดียวกันเสมอ
 */

namespace Dms\Helper;

use Gcms\Api as ApiController;

class Controller extends \Kotchasan\KBase
{
    /**
     * สิทธิ์ของโมดูล (ชื่อเดียวกับระบบเดิม)
     */
    const PERMISSIONS = [
        'can_manage_dms' => '{LNG_Can manage the} {LNG_Document management system}',
        'can_download_dms' => '{LNG_Can view or download file} ({LNG_Document management system})',
        'can_upload_dms' => '{LNG_Can upload your document file} ({LNG_Document management system})'
    ];

    /**
     * รายการสิทธิ์ในรูป [{value, text}]
     *
     * @return array
     */
    public static function permissionOptions()
    {
        $result = [];
        foreach (self::PERMISSIONS as $value => $text) {
            $result[] = ['value' => $value, 'text' => $text];
        }

        return $result;
    }

    /**
     * ดูและดาวน์โหลดเอกสารได้
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canDownload($login)
    {
        return ApiController::hasPermission($login, 'can_download_dms');
    }

    /**
     * อัปโหลดเอกสารได้
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canUpload($login)
    {
        return ApiController::hasPermission($login, 'can_upload_dms');
    }

    /**
     * จัดการตู้เก็บเอกสารได้
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canManage($login)
    {
        return ApiController::hasPermission($login, 'can_manage_dms');
    }

    /**
     * แผนกของสมาชิก (user_meta name = department)
     *
     * @param object|null $login
     *
     * @return array
     */
    public static function departments($login)
    {
        $result = [];
        if ($login && isset($login->metas['department']) && is_array($login->metas['department'])) {
            foreach ($login->metas['department'] as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $result[] = $value;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * ผู้อัปโหลดถูกจำกัดให้ทำงานกับแผนกของตัวเองเท่านั้นหรือไม่
     * (ตั้งค่า "อัปโหลดได้เฉพาะแผนกของตัวเอง" · ผู้ดูแลระบบและสมาชิกที่ไม่มีแผนกไม่ถูกจำกัด
     * เหมือนระบบเดิม)
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function isUploadRestricted($login)
    {
        return !empty(self::$cfg->dms_upload_options)
            && !ApiController::isAdmin($login)
            && !empty(self::departments($login));
    }

    /**
     * แก้ไข ลบ และดูไฟล์/ประวัติดาวน์โหลดของเอกสารนี้ได้หรือไม่
     *
     * ⚠️ ระบบเดิมตรวจความเป็นเจ้าของเฉพาะหน้าแก้ไข แต่ปล่อยให้ลบ ดูไฟล์ และดูประวัติ
     * ของเอกสารคนอื่นได้ ที่นี่ใช้กติกาเดียวกันทุกการกระทำ
     *
     * @param object|null $login
     * @param object $document อย่างน้อยต้องมี member_id
     *
     * @return bool
     */
    public static function canEditDocument($login, $document)
    {
        if (!$document || !self::canUpload($login)) {
            return false;
        }

        return !self::isUploadRestricted($login) || (int) $document->member_id === (int) $login->id;
    }

    /**
     * หมวดหมู่ของเอกสาร (แผนก + ตู้เก็บเอกสาร) แบบไม่ใช้แคช
     *
     * ⚠️ Gcms\Category::init() แคชผลไว้ตามค่าปริยาย ตู้ที่เพิ่งพิมพ์สร้างจากฟอร์มเอกสาร
     * จะยังไม่ขึ้นในตัวกรองและตัวเลือกจนกว่าแคชหมดอายุ ตารางเล็ก อ่านใหม่ทุกครั้งได้
     *
     * @return \Dms\Category\Controller
     */
    public static function category()
    {
        return \Dms\Category\Controller::init(true, true, false);
    }

    /**
     * แผนกที่เลือกได้ตอนบันทึกเอกสาร [{value, text}]
     *
     * @param object|null $login
     *
     * @return array
     */
    public static function departmentOptions($login)
    {
        $category = self::category();
        if (self::isUploadRestricted($login)) {
            return $category->toOptions('department', true, self::departments($login));
        }

        return $category->toOptions('department');
    }

    /**
     * ที่อยู่ของไอคอนชนิดไฟล์
     *
     * @param string $ext
     *
     * @return string
     */
    public static function extIcon($ext)
    {
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower((string) $ext));

        return WEB_URL.'images/ext/'.($ext !== '' && is_file(ROOT_PATH.'images/ext/'.$ext.'.png') ? $ext : 'file').'.png';
    }

    /**
     * โฟลเดอร์เก็บไฟล์ของเอกสาร (สร้างให้ถ้ายังไม่มี) คืนค่า false ถ้าสร้างไม่ได้
     *
     * ไฟล์ทุกไฟล์ต้องเปิดผ่าน api/dms/download เท่านั้น (ตรวจสิทธิ์ + นับครั้ง)
     * จึงปิดการเข้าถึง datas/dms/ โดยตรงไว้ด้วย .htaccess
     *
     * @param int $id
     *
     * @return string|false
     */
    public static function documentDir($id)
    {
        $base = ROOT_PATH.DATA_FOLDER.'dms/';
        if (!\Kotchasan\File::makeDirectory($base)) {
            return false;
        }
        if (!is_file($base.'.htaccess')) {
            @file_put_contents($base.'.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        $dir = $base.(int) $id.'/';

        return \Kotchasan\File::makeDirectory($dir) ? $dir : false;
    }

    /**
     * ลบไฟล์ทั้งหมดของเอกสาร (ทั้งโฟลเดอร์)
     *
     * @param int $id
     */
    public static function removeDocumentDir($id)
    {
        $dir = ROOT_PATH.DATA_FOLDER.'dms/'.(int) $id.'/';
        if (is_dir($dir)) {
            \Kotchasan\File::removeDirectory($dir);
        }
    }
}
