<?php
/**
 * @filesource modules/dms/controllers/files.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Files;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms-files
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * แสดงรายการไฟล์
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // สมาชิก
        $login = Login::isMember();
        // ตรวจสอบรายการที่เลือก
        $index = \Dms\Write\Model::get($request->request('id')->toInt(), $login);
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_List of} {LNG_File}');
        // เลือกเมนู
        $this->menu = 'dms';
        // สามารถอัปโหลดได้
        if ($index && Login::checkPermission($login, 'can_upload_dms')) {
            // ข้อความ title bar
            $this->title .= ' '.$index->document_no;
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-edocument">{LNG_Document management system}</span></li>');
            $ul->appendChild('<li><a href="{BACKURL?module=dms-setup&id=0}">{LNG_Upload} {LNG_Document}</a></li>');
            $ul->appendChild('<li><span>{LNG_List of} {LNG_File}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-documents">'.$this->title.'</h2>'
            ));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // รายการไฟล์
            $div->appendChild(\Dms\Files\View::create()->render($request, $index));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
