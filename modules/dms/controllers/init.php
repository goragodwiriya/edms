<?php
/**
 * @filesource modules/dms/controllers/init.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Init;

/**
 * Init Module
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * รายการ permission ของโมดูล
     *
     * @param array $permissions
     *
     * @return array
     */
    public static function updatePermissions($permissions)
    {
        $permissions['can_manage_dms'] = '{LNG_Can manage the} {LNG_Document management system}';
        $permissions['can_download_dms'] = '{LNG_Can view or download file} ({LNG_Document management system})';
        $permissions['can_upload_dms'] = '{LNG_Can upload your document file} ({LNG_Document management system})';
        return $permissions;
    }

    /**
     * สมัครสมาชิก ใช้ค่าเริ่มต้นของโมดูล
     *
     * @param array $permissions
     * @param array $user
     *
     * @return array
     */
    public static function newRegister($permissions, $user)
    {
        return empty(self::$cfg->dms_user_permission) ? $permissions : array_merge($permissions, self::$cfg->dms_user_permission);
    }
}
