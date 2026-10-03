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
        // ตั้งค่าของโมดูล — อยู่ใต้เมนู "ตั้งค่า" ซึ่งมีเฉพาะผู้ที่มี can_config
        // ผู้ที่จัดการตู้เก็บเอกสารได้แต่ไม่มีเมนูตั้งค่า จะได้เมนูตู้เก็บเอกสารใต้เมนูของโมดูลแทน
        $settings = [];
        if (ApiController::hasPermission($login, 'can_config')) {
            $settings[] = [
                'title' => '{LNG_Module Settings}',
                'url' => '/dms-settings',
                'icon' => 'icon-cog'
            ];
        }
        if (Helper::canManage($login)) {
            foreach (\Dms\Categories\Controller::items() as $type => $label) {
                $settings[] = [
                    'title' => $label,
                    'url' => '/dms-categories?type='.$type,
                    'icon' => 'icon-tags'
                ];
            }
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
    public static function initDashboard($cards, $params = null, $login = null)
    {
        if (!$login || !Helper::canDownload($login)) {
            return $cards;
        }
        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d');
        // หน้าแรกผูกค่าด้วย data-text ซึ่งไม่แปลง {LNG_} ให้ — แปลงที่นี่
        $cards[] = [
            'title' => Language::get('New document'),
            'value' => number_format(\Dms\Dashboard\Model::countNew($login, $from, $to)),
            'unit' => '',
            'icon' => 'icon-edocument',
            'url' => '/dms?from='.$from.'&to='.$to,
            'hint' => '30 '.Language::get('days'),
            'class' => 'positive'
        ];

        return $cards;
    }
}
