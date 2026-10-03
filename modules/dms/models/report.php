<?php
/**
 * @filesource modules/dms/models/report.php
 */

namespace Dms\Report;

class Model extends \Kotchasan\Model
{
    /**
     * ประวัติการดาวน์โหลดของไฟล์ — 1 แถวต่อ 1 สมาชิก
     *
     * @param array $params ต้องมี dms_id และ file_id
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params)
    {
        $query = static::createQuery()
            ->select('D.id', 'D.member_id', 'U.status', 'U.name', 'D.updated_at', 'D.downloads')
            ->from('dms_download D')
            ->join('user U', [['U.id', 'D.member_id']], 'LEFT')
            ->where([
                ['D.dms_id', (int) $params['dms_id']],
                ['D.file_id', (int) $params['file_id']]
            ]);
        if ($params['search'] !== '') {
            $query->where(['U.name', 'LIKE', '%'.$params['search'].'%']);
        }

        return $query;
    }
}
