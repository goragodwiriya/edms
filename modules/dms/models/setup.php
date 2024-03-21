<?php
/**
 * @filesource modules/dms/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Setup;

use Gcms\Login;
use Kotchasan;
use Kotchasan\Database\Sql;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms-setup
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
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = [];
        if (!empty($params['member_id'])) {
            $where[] = array('A.member_id', $params['member_id']);
        }
        if (!empty($params['from'])) {
            $where[] = array('A.create_date', '>=', $params['from']);
        }
        if (!empty($params['to'])) {
            $where[] = array('A.create_date', '<=', $params['to']);
        }
        $select = array('A.id', 'A.create_date', 'A.document_no', 'A.topic');
        $query = static::createQuery()
            ->from('dms A');
        $n = 1;
        foreach (Language::get('DMS_CATEGORIES', []) as $k => $label) {
            $query->join('dms_meta N'.$n, 'LEFT', array(array('N'.$n.'.dms_id', 'A.id'), array('N'.$n.'.type', $k)))
                ->join('category C'.$n, 'LEFT', array(array('C'.$n.'.category_id', 'N'.$n.'.value'), array('C'.$n.'.type', $k)));
            if ($k == 'department') {
                $select[] = Sql::GROUP_CONCAT('C'.$n.'.topic', $k, ', ');
            } else {
                $select[] = 'C'.$n.'.topic '.$k;
            }
            if (!empty($params[$k])) {
                $where[] = array('N'.$n.'.value', $params[$k]);
            }
            $n++;
        }
        $select[] = 'A.url';
        return $query->select($select)
            ->where($where)
            ->groupBy('A.id');
    }

    /**
     * รับค่าจาก action (setup.php)
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
                        $ids = explode(',', $id);
                        // ลบไฟล์
                        foreach ($ids as $id) {
                            File::removeDirectory(ROOT_PATH.DATA_FOLDER.'dms/'.$id.'/');
                        }
                        // ลบข้อมูล
                        $this->db()->delete($this->getTableName('dms'), array('id', $ids), 0);
                        $this->db()->delete($this->getTableName('dms_files'), array('dms_id', $ids), 0);
                        $this->db()->delete($this->getTableName('dms_download'), array('dms_id', $ids), 0);
                        $this->db()->delete($this->getTableName('dms_meta'), array('dms_id', $ids), 0);
                        // Log
                        \Index\Log\Model::add(0, 'dms', 'Delete', '{LNG_Delete} {LNG_Document} ID : '.implode(', ', $ids), $login['id']);
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
