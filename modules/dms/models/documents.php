<?php
/**
 * @filesource modules/dms/models/documents.php
 */

namespace Dms\Documents;

use Dms\Helper\Controller as Helper;
use Kotchasan\Database\Sql;

class Model extends \Kotchasan\Model
{
    /**
     * รายการเอกสารสำหรับผู้ดาวน์โหลด — 1 แถวต่อ 1 ไฟล์ (เอกสารแบบ URL เป็น 1 แถว)
     *
     * สมาชิกที่มีแผนกเห็นเฉพาะเอกสารที่ส่งถึงแผนกของตัวเอง สมาชิกที่ไม่มีแผนกเห็นทั้งหมด
     * (เหมือนระบบเดิม) · downloads คือจำนวนครั้งที่ "ผู้ใช้คนนี้" ดาวน์โหลดไฟล์นั้น
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params, $login)
    {
        $query = static::createQuery()
            ->select(
                'A.id dms_id',
                Sql::IFNULL('F.id', 0, 'file_id'),
                'A.create_date',
                'A.document_no',
                'A.topic',
                'A.url',
                'F.topic file_name',
                'F.ext',
                Sql::GROUP_CONCAT('C1.topic', 'department', ', ', true),
                Sql::MAX('C2.topic', 'cabinet'),
                Sql::MAX('W.downloads', 'downloads')
            )
            ->from('dms A')
            ->join('dms_files F', [['F.dms_id', 'A.id']], 'LEFT')
            ->join('dms_meta N1', [['N1.dms_id', 'A.id'], ['N1.type', 'department']], 'LEFT')
            ->join('category C1', [['C1.category_id', 'N1.value'], ['C1.type', 'department']], 'LEFT')
            ->join('dms_meta N2', [['N2.dms_id', 'A.id'], ['N2.type', 'cabinet']], 'LEFT')
            ->join('category C2', [['C2.category_id', 'N2.value'], ['C2.type', 'cabinet']], 'LEFT')
            ->join('dms_download W', [
                ['W.dms_id', 'A.id'],
                ['W.member_id', (int) $login->id],
                "`W`.`file_id` = CASE WHEN `A`.`url` = '' THEN `F`.`id` ELSE 0 END"
            ], 'LEFT')
            ->groupBy(['A.id', 'F.id']);
        if (!empty($params['from'])) {
            $query->where(['A.create_date', '>=', $params['from']]);
        }
        if (!empty($params['to'])) {
            $query->where(['A.create_date', '<=', $params['to']]);
        }
        if ($params['cabinet'] !== '') {
            $query->where(['N2.value', $params['cabinet']]);
        }
        $departments = Helper::departments($login);
        if (!empty($departments)) {
            $query->whereExists('dms_meta V', [
                ['V.dms_id', 'A.id'],
                ['V.type', 'department'],
                ['V.value', $departments]
            ]);
        }
        if ($params['search'] !== '') {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['A.topic', 'LIKE', $search],
                ['A.document_no', 'LIKE', $search],
                ['F.topic', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }
}
