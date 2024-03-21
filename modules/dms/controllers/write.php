<?php
/**
 * @filesource modules/dms/controllers/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Write;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟอร์มสร้าง/แก้ไข เอกสาร
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Document');
        // เลือกเมนู
        $this->menu = 'dms';
        // สมาชิก
        $login = Login::isMember();
        // สามารถอัปโหลดได้
        if (Login::checkPermission($login, 'can_upload_dms')) {
            // ตรวจสอบรายการที่เลือก
            $index = \Dms\Write\Model::get($request->request('id')->toInt(), $login);
            if ($index && $login['status'] != 1 && !empty(self::$cfg->dms_upload_options) && $index->member_id !== $login['id']) {
                $index = null;
            }
            // สามารถอัปโหลดได้
            if ($index) {
                // ข้อความ title bar
                $title = Language::get(empty($index->id) ? 'Upload' : 'Edit');
                $this->title = $title .= ' '.$this->title;
                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', array(
                    'class' => 'breadcrumbs'
                ));
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><span class="icon-edocument">{LNG_Document management system}</span></li>');
                $ul->appendChild('<li><a href="{BACKURL?module=dms-setup}">{LNG_Document}</a></li>');
                $ul->appendChild('<li><span>'.$title.'</span></li>');
                $section->add('header', array(
                    'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
                ));
                $div = $section->add('div', array(
                    'class' => 'content_bg'
                ));
                // แสดงฟอร์ม
                $div->appendChild(\Dms\Write\View::create()->render($index, $login));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
