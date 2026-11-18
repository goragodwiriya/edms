<?php
/**
 * @filesource modules/dms/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Init;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Language;

class Controller extends \Gcms\Controller
{
    /**
     * สิทธิ์ของโมดูล
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        foreach (Helper::permissionOptions() as $item) {
            $permissions[] = $item;
        }

        return $permissions;
    }

    /**
     * เมนูของโมดูล
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login) {
            return $menus;
        }
        $children = [];
        if (Helper::canDownload($login)) {
            $children[] = [
                'title' => '{LNG_List of} {LNG_Document}',
                'url' => '/dms',
                'icon' => 'icon-documents'
            ];
        }
        if (Helper::canUpload($login)) {
            $children[] = [
                'title' => '{LNG_Upload} {LNG_Document}',
                'url' => '/dms-setup',
                'icon' => 'icon-upload'
            ];
        }
        // ตั้งค่าของโมดูล — อยู่ใต้เมนู "ตั้งค่า" ซึ่ง Index\Menus เปิดให้ทุกคนที่เข้าระบบ
        // จึงตรวจสิทธิ์ที่นี่: ตั้งค่าโมดูลต้องมี can_config ส่วนตู้เก็บเอกสารใช้ can_manage_dms
        // (ถ้าไม่มีกลุ่มตั้งค่า เมนูเหล่านี้จะอยู่ใต้เมนูของโมดูลแทน)
        $settings = [];
        if (ApiController::hasPermission($login, 'can_config')) {
            $settings[] = [
                'title' => '{LNG_Module Settings}',
                'url' => '/dms-settings',
                'icon' => 'icon-cog'
            ];
        }
        if (Helper::canManage($login)) {
            // Only cabinets are managed here; departments are on the core page
            $settings[] = [
                'title' => '{LNG_Cabinet}',
                'url' => '/dms-categories?type=cabinet',
                'icon' => 'icon-tags'
            ];
        }
        $hasSettingsMenu = parent::hasMenu($menus, 'settings');
        if (!$hasSettingsMenu) {
            $children = array_merge($children, $settings);
        }
        if (count($children) === 1) {
            $menus = parent::insertMenuAfter($menus, [
                [
                    'title' => '{LNG_Document management system}',
                    'url' => $children[0]['url'],
                    'icon' => 'icon-edocument'
                ]
            ], 0);
        } elseif (!empty($children)) {
            $menus = parent::insertMenuAfter($menus, [
                [
                    'title' => '{LNG_Document management system}',
                    'icon' => 'icon-edocument',
                    'children' => $children
                ]
            ], 0);
        }
        if ($hasSettingsMenu && !empty($settings)) {
            $menus = parent::insertMenuChildren($menus, [
                [
                    'title' => '{LNG_Document management system}',
                    'icon' => 'icon-edocument',
                    'children' => $settings
                ]
            ], 'settings', null, 1);
        }

        // หมวดหมู่ของบุคลากรเป็นแบบหลายภาษา ใช้หน้าของโมดูลแทนหน้าแผนกของแกน
        // (หน้าของแกนบันทึกแบบภาษาเดียว กดบันทึกแล้วคำแปลภาษาอื่นหายหมด)
        if (isset($menus['settings']['children'])) {
            $menus['settings']['children'] = array_values(array_filter($menus['settings']['children'], function ($item) {
                return !isset($item['url']) || $item['url'] !== '/categories?type=department';
            }));
        }

        return $menus;
    }

    /**
     * การ์ดบนหน้าแรก — เอกสารใหม่ 30 วันที่ยังไม่ได้ดาวน์โหลด
     *
     * @param array $cards
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initDashboardCards($cards, $params = null, $login = null)
    {
        if (!$login || !Helper::canDownload($login)) {
            return $cards;
        }
        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d');
        // ข้อมูลล้วน: จำนวนดิบ · บรรทัดล่าง "30 วัน" เป็นชิ้นส่วน (หน้าแรกจัดรูปและแปลเอง)
        $cards[] = [
            'title' => Language::get('New document'),
            'value' => \Dms\Dashboard\Model::countNew($login, $from, $to),
            'unit' => '',
            'icon' => 'icon-edocument',
            'url' => '/dms?from='.$from.'&to='.$to,
            'hint' => [['number' => 30], ['text' => Language::get('days')]],
            'class' => 'positive'
        ];

        return $cards;
    }
}
