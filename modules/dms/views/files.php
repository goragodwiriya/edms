<?php
/**
 * @filesource modules/dms/views/files.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Files;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Text;

/**
 * module=dms-files
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงรายการไฟล์
     *
     * @param Request $request
     * @param object $index
     *
     * @return string
     */
    public function render(Request $request, $index)
    {
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Dms\Files\Model::toDataTable($index->id),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('dmsFiles_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => 'create_date DESC',
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id', 'file'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/dms/model/files/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => array(
                array(
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => array(
                        'delete' => '{LNG_Delete}'
                    )
                )
            ),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic'),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'topic' => array(
                    'text' => '{LNG_Document title}'
                ),
                'ext' => array(
                    'text' => '',
                    'class' => 'center'
                ),
                'size' => array(
                    'text' => '{LNG_Size of} {LNG_File}',
                    'class' => 'center'
                ),
                'create_date' => array(
                    'text' => '{LNG_Date}'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'ext' => array(
                    'class' => 'center'
                ),
                'size' => array(
                    'class' => 'center'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'download' => array(
                    'class' => 'icon-download button purple',
                    'href' => WEB_URL.DATA_FOLDER.':file',
                    'target' => 'download',
                    'text' => '{LNG_Download}'
                ),
                'report' => array(
                    'class' => 'icon-report button orange',
                    'href' => $uri->createBackUri(array('module' => 'dms-report', 'id' => ':id')),
                    'text' => '{LNG_Download history}'
                )
            ),
            /* ปุมเพิ่ม */
            'addNew' => array(
                'class' => 'float_button icon-upload',
                'href' => $uri->createBackUri(array('module' => 'dms-write', 'id' => $index->id)),
                'title' => '{LNG_Upload} {LNG_File}'
            )
        ));
        // save cookie
        setcookie('dmsFiles_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array $item
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['create_date'] = Date::format($item['create_date']);
        $item['ext'] = '<img src="'.(is_file(ROOT_PATH.'skin/ext/'.$item['ext'].'.png') ? WEB_URL.'skin/ext/'.$item['ext'].'.png' : WEB_URL.'skin/ext/file.png').'" alt="'.$item['ext'].'">';
        $item['size'] = Text::formatFileSize($item['size']);
        return $item;
    }
}
