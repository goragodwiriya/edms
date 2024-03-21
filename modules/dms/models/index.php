<?php
/**
 * @filesource modules/dms/models/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Index;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms
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
     * @param array $login
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params, $login)
    {
        $where = [];
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
                $select[] = Sql::GROUP_CONCAT('C'.$n.'.topic', $k);
                if (!empty($login['department'])) {
                    $where[] = array('N'.$n.'.value', $login['department']);
                }
            } else {
                $select[] = 'C'.$n.'.topic '.$k;
            }
            if (!empty($params[$k])) {
                $where[] = array('N'.$n.'.value', $params[$k]);
            }
            $n++;
        }
        $select[] = 'A.url';
        $q1 = $query->select($select)
            ->where($where)
            ->groupBy('A.id');
        return static::createQuery()
            ->select(Sql::IFNULL('F.id', 0, 'id'), 'A.id dms_id', 'A.create_date', 'A.document_no', 'A.topic', 'F.topic file_name', 'F.ext', 'A.department', 'A.cabinet', 'W.downloads', 'A.url')
            ->from(array($q1, 'A'))
            ->join('dms_files F', 'LEFT', array('F.dms_id', 'A.id'))
            ->join('dms_download W', 'LEFT', array(array('W.file_id', Sql::create('CASE WHEN A.`url`="" THEN F.`id` ELSE 0 END')), array('W.dms_id', 'A.id'), array('W.member_id', $login['id'])));
    }

    /**
     * รับค่าจาก action (index.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, member, สามารถดูหรือดาวน์โหลดเอกสารได้
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_download_dms')) {
                // ค่าที่ส่งมา
                $file_id = $request->post('id')->toInt();
                if (preg_match('/(detail|download)_([0-9]+)/', $request->post('action')->toString(), $match)) {
                    if ($match[1] == 'detail') {
                        $document = \Dms\View\Model::get($match[2]);
                        if ($document) {
                            $ret['modal'] = Language::trans(\Dms\View\View::create()->render($document, $login));
                        }
                    } elseif ($match[1] == 'download') {
                        if ($file_id > 0) {
                            // ดาวน์โหลดไฟล์
                            $ret = $this->fileDownload($file_id, $login['id']);
                        } else {
                            // เปิด URL
                            $ret = $this->openUrl($match[2], $login['id']);
                        }
                    }
                }
            }
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * เปิด URL
     *
     * @param int $id
     * @param int $member_id
     *
     * @return array
     */
    public function openUrl($id, $member_id)
    {
        $download = $this->db()->createQuery()
            ->from('dms_download')
            ->where(array(
                array('dms_id', $id),
                array('file_id', 0),
                array('member_id', $member_id)
            ))
            ->first('id', 'downloads');
        $save = array(
            'downloads' => $download ? $download->downloads + 1 : 1,
            'dms_id' => $id,
            'file_id' => 0,
            'member_id' => $member_id,
            'last_update' => date('Y-m-d H:i:s')
        );
        if ($download) {
            $this->db()->update($this->getTableName('dms_download'), $download->id, $save);
        } else {
            $this->db()->insert($this->getTableName('dms_download'), $save);
        }
        return [];
    }

    /**
     * ดาวน์โหลดไฟล์
     *
     * @param int $file_id
     * @param int $member_id
     *
     * @return array
     */
    public function fileDownload($file_id, $member_id)
    {
        $ret = [];
        // อ่านรายการที่เลือก
        $result = $this->db()->createQuery()
            ->from('dms_files')
            ->where(array('id', $file_id))
            ->first('id', 'dms_id', 'size', 'name', 'file', 'ext');
        if ($result) {
            // ไฟล์
            $file = ROOT_PATH.DATA_FOLDER.$result->file;
            if (is_file($file)) {
                // สามารถดาวน์โหลดได้
                $download = $this->db()->createQuery()
                    ->from('dms_download')
                    ->where(array(
                        array('file_id', $result->id),
                        array('member_id', $member_id)
                    ))
                    ->first('id', 'downloads');
                $save = array(
                    'downloads' => $download ? $download->downloads + 1 : 1,
                    'dms_id' => $result->dms_id,
                    'file_id' => $result->id,
                    'member_id' => $member_id,
                    'last_update' => date('Y-m-d H:i:s')
                );
                if ($download) {
                    $this->db()->update($this->getTableName('dms_download'), $download->id, $save);
                } else {
                    $this->db()->insert($this->getTableName('dms_download'), $save);
                }
                // id สำหรบไฟล์ดาวน์โหลด
                $id = uniqid();
                // บันทึกรายละเอียดการดาวน์โหลดลง SESSION
                $file = array(
                    'file' => $file,
                    'size' => $result->size
                );
                if (self::$cfg->dms_download_action == 0 || !in_array($result->ext, self::$cfg->know_file_typies)) {
                    $file['name'] = $result->name.'.'.$result->ext;
                } else {
                    $file['name'] = '';
                }
                if (self::$cfg->dms_download_action == 1) {
                    $file['mime'] = \Kotchasan\Mime::get($result->ext);
                } else {
                    $file['mime'] = 'application/octet-stream';
                }
                $_SESSION[$id] = $file;
                // คืนค่า
                $ret['open'] = WEB_URL.'modules/dms/filedownload.php?id='.$id;
            } else {
                // ไม่พบไฟล์
                $ret['alert'] = Language::get('File not found');
            }
        }
        return $ret;
    }
}
