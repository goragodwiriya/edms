<?php
/**
 * @filesource modules/dms/controllers/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Report;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * แสดงประวัติการดาวน์โหลด
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ตรวจสอบรายการที่เลือก
        $index = \Dms\Report\Model::get($request->request('id')->toInt());
        // ข้อความ title bar
        $this->title = Language::get('Download history');
        // เลือกเมนู
        $this->menu = 'dms';
        // สามารถอัปโหลดได้
        if ($index && Login::checkPermission(Login::isMember(), 'can_upload_dms')) {
            // ข้อความ title bar
            $this->title .= ' '.$index->topic;
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-edocument">{LNG_Document management system}</span></li>');
            $ul->appendChild('<li><a href="{BACKURL?module=dms-setup&id=0}">{LNG_Upload} {LNG_Document}</a></li>');
            $ul->appendChild('<li><a href="{BACKURL?module=dms-files&id='.$index->dms_id.'}">{LNG_List of} {LNG_File}</a></li>');
            $ul->appendChild('<li><span>{LNG_Download}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-download">'.$this->title.'</h2>'
            ));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // ประวัติการดาวน์โหลด
            $div->appendChild(\Dms\Report\View::create()->render($request, $index));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
