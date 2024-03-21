<?php
/**
 * @filesource modules/dms/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Init Menus
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นโหลดเมนูทั้งหมด
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        // สามารถดาวน์โหลดเอกสารได้
        $can_download = Login::checkPermission($login, 'can_download_dms');
        // สามารถอัปโหลดเอกสารได้
        $can_upload = Login::checkPermission($login, 'can_upload_dms');
        if ($can_download && $can_upload) {
            $menu->addTopLvlMenu('dms', '{LNG_Document management system}', null, array(
                array(
                    'text' => '{LNG_List of}',
                    'url' => 'index.php?module=dms'
                ),
                array(
                    'text' => '{LNG_Upload}',
                    'url' => 'index.php?module=dms-setup'
                )
            ), 'member');
        } elseif ($can_download) {
            $menu->addTopLvlMenu('dms', '{LNG_Document management system}', 'index.php?module=dms', null, 'member');
        } elseif ($can_upload) {
            $menu->addTopLvlMenu('dms', '{LNG_Document management system}', 'index.php?module=dms-setup', null, 'member');
        }
        // เมนูตั้งค่า
        $submenus = [];
        if (Login::checkPermission($login, 'can_config')) {
            $submenus[] = array(
                'text' => '{LNG_Settings}',
                'url' => 'index.php?module=dms-settings'
            );
        }
        if (Login::checkPermission($login, 'can_manage_dms')) {
            foreach (Language::get('DMS_CATEGORIES') as $type => $text) {
                if ($type != 'department') {
                    $submenus[] = array(
                        'text' => $text,
                        'url' => 'index.php?module=dms-categories&amp;type='.$type
                    );
                }
            }
        }
        if (!empty($submenus)) {
            $menu->add('settings', '{LNG_Document management system}', null, $submenus, 'dms');
        }
    }
}
