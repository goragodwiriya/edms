<?php
/**
 * @filesource modules/dms/models/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Report;

/**
 * module=dms-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลที่เลือก
     *
     * @param int $id ID
     *
     * @return object|null คืนค่าข้อมูล object ไม่พบคืนค่า null
     */
    public static function get($id)
    {
        return static::createQuery()
            ->from('dms_files')
            ->where(array('id', $id))
            ->first();
    }

    /**
     * อ่านข้อมูลประวัติการดาวน์โหลดใส่ลงในตาราง.
     *
     * @param int $id ID
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($id)
    {
        return static::createQuery()
            ->select('D.id', 'U.status', 'U.name', 'D.last_update', 'D.downloads')
            ->from('dms_download D')
            ->join('user U', 'LEFT', array('U.id', 'D.member_id'))
            ->where(array('D.file_id', $id));
    }
}
