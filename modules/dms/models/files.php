<?php
/**
 * @filesource modules/dms/models/files.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Files;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms-files
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param int $id
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($id)
    {
        return static::createQuery()
            ->select('id', 'topic', 'ext', 'size', 'create_date', 'file')
            ->from('dms_files')
            ->where(array('dms_id', $id));
    }

    /**
     * รับค่าจาก action (files.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, สามารถอัปโหลดได้, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_upload_dms') && Login::notDemoMode($login)) {
                // รับค่าจากการ POST
                $id = $request->post('id')->toString();
                $action = $request->post('action')->toString();
                // ตรวจสอบค่าที่ส่งมา
                if (preg_match('/^[0-9,]+$/', $id)) {
                    if ($action === 'delete') {
                        // ลบ
                        $query = $this->db()->createQuery()
                            ->select('id', 'file')
                            ->from('dms_files')
                            ->where(array('id', explode(',', $id)));
                        $ids = [];
                        foreach ($query->execute() as $item) {
                            $ids[] = $item->id;
                            // ลบไฟล์
                            unlink(ROOT_PATH.DATA_FOLDER.$item->file);
                        }
                        // ลบข้อมูล
                        $this->db()->delete($this->getTableName('dms_files'), array('id', $ids), 0);
                        $this->db()->delete($this->getTableName('dms_download'), array('id', $ids), 0);
                        // Log
                        \Index\Log\Model::add(0, 'dms', 'Delete', '{LNG_Delete} {LNG_File} ID : '.implode(', ', $ids), $login['id']);
                        // reload
                        $ret['location'] = 'reload';
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
