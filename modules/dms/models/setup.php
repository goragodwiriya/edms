<?php
/**
 * @filesource modules/dms/models/setup.php
 */

namespace Dms\Setup;

use Dms\Helper\Controller as Helper;
use Kotchasan\Database\Sql;

class Model extends \Kotchasan\Model
{
    /**
     * รายการเอกสารสำหรับผู้อัปโหลด — 1 แถวต่อ 1 เอกสาร
     *
     * $params['department'] เป็น array ได้ (ผู้ที่ถูกจำกัดเฉพาะแผนกตัวเองเห็นทุกแผนกของตัวเอง)
     * กรองด้วย EXISTS เพื่อให้คอลัมน์แผนกยังแสดงครบทุกแผนกของเอกสาร
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params)
    {
        $query = static::createQuery()
            ->select(
                'A.id',
                'A.member_id',
                'A.create_date',
                'A.document_no',
                'A.topic',
                'A.url',
                Sql::GROUP_CONCAT('C1.topic', 'department', ', ', true),
                Sql::MAX('C2.topic', 'cabinet')
            )
            ->from('dms A')
            ->join('dms_meta N1', [['N1.dms_id', 'A.id'], ['N1.type', 'department']], 'LEFT')
            ->join('category C1', [['C1.category_id', 'N1.value'], ['C1.type', 'department']], 'LEFT')
            ->join('dms_meta N2', [['N2.dms_id', 'A.id'], ['N2.type', 'cabinet']], 'LEFT')
            ->join('category C2', [['C2.category_id', 'N2.value'], ['C2.type', 'cabinet']], 'LEFT')
            ->groupBy('A.id');
        if (!empty($params['from'])) {
            $query->where(['A.create_date', '>=', $params['from']]);
        }
        if (!empty($params['to'])) {
            $query->where(['A.create_date', '<=', $params['to']]);
        }
        if (!empty($params['department'])) {
            $query->whereExists('dms_meta V', [
                ['V.dms_id', 'A.id'],
                ['V.type', 'department'],
                ['V.value', (array) $params['department']]
            ]);
        }
        if ($params['cabinet'] !== '') {
            $query->where(['N2.value', $params['cabinet']]);
        }
        if ($params['search'] !== '') {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['A.topic', 'LIKE', $search],
                ['A.document_no', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * ลบเอกสาร ไฟล์ หมวดหมู่ และประวัติดาวน์โหลด
     *
     * @param array $ids
     *
     * @return int จำนวนเอกสารที่ลบ
     */
    public static function remove(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }
        foreach ($ids as $id) {
            Helper::removeDocumentDir($id);
        }
        $db = \Kotchasan\DB::create();
        $db->delete('dms_files', [['dms_id', $ids]], 0);
        $db->delete('dms_download', [['dms_id', $ids]], 0);
        $db->delete('dms_meta', [['dms_id', $ids]], 0);

        return (int) $db->delete('dms', [['id', $ids]], 0);
    }
}
