<?php
/**
 * @filesource modules/dms/models/dashboard.php
 */

namespace Dms\Dashboard;

use Dms\Helper\Controller as Helper;
use Kotchasan\Database\Sql;

class Model extends \Kotchasan\Model
{
    /**
     * จำนวนเอกสารใหม่ในช่วงวันที่ ที่ผู้ใช้ยังดาวน์โหลดไม่ครบ (เห็นได้ตามกติกาเดียวกับรายการเอกสาร)
     *
     * ⚠️ ระบบเดิมนับเป็นแถวของไฟล์ x แผนก เอกสาร 1 ฉบับที่ส่งถึง 3 แผนกจึงถูกนับ 3 ครั้ง
     * ที่นี่นับเป็นจำนวนเอกสาร
     *
     * @param object $login
     * @param string $from
     * @param string $to
     *
     * @return int
     */
    public static function countNew($login, $from, $to)
    {
        $query = static::createQuery()
            ->select(Sql::COUNT('A.id', 'count', true))
            ->from('dms A')
            ->join('dms_files F', [['F.dms_id', 'A.id']], 'LEFT')
            ->where([
                ['A.create_date', '>=', $from],
                ['A.create_date', '<=', $to]
            ])
            ->whereNotExists('dms_download W', [
                ['W.dms_id', 'A.id'],
                ['W.member_id', (int) $login->id],
                "`W`.`file_id` = CASE WHEN `A`.`url` = '' THEN IFNULL(`F`.`id`, 0) ELSE 0 END"
            ]);
        $departments = Helper::departments($login);
        if (!empty($departments)) {
            $query->whereExists('dms_meta V', [
                ['V.dms_id', 'A.id'],
                ['V.type', 'department'],
                ['V.value', $departments]
            ]);
        }
        $result = $query->first();

        return $result ? (int) $result->count : 0;
    }
}
