<?php
/**
 * @filesource modules/dms/models/files.php
 */

namespace Dms\Files;

class Model extends \Kotchasan\Model
{
    /**
     * ไฟล์ของเอกสาร
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params)
    {
        $query = static::createQuery()
            ->select('id', 'dms_id', 'topic', 'ext', 'size', 'created_at')
            ->from('dms_files')
            ->where(['dms_id', (int) $params['id']]);
        if ($params['search'] !== '') {
            $query->where(['topic', 'LIKE', '%'.$params['search'].'%']);
        }

        return $query;
    }

    /**
     * ลบไฟล์ที่เลือกของเอกสาร พร้อมประวัติการดาวน์โหลดไฟล์เหล่านั้น
     *
     * ⚠️ ระบบเดิมลบ dms_download ด้วย id ของไฟล์แทน file_id ประวัติการดาวน์โหลดของ
     * เอกสารอื่นจึงหายไปแทน ส่วนของไฟล์ที่ลบกลับค้างอยู่
     *
     * @param int $dms_id
     * @param array $ids
     *
     * @return array id ของไฟล์ที่ลบจริง
     */
    public static function remove($dms_id, array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }
        $removed = [];
        $query = static::createQuery()
            ->select('id', 'file')
            ->from('dms_files')
            ->where([
                ['dms_id', (int) $dms_id],
                ['id', $ids]
            ]);
        foreach ($query->fetchAll() as $item) {
            $removed[] = (int) $item->id;
            $path = ROOT_PATH.DATA_FOLDER.$item->file;
            if ($item->file !== null && $item->file !== '' && strpos($item->file, '..') === false && is_file($path)) {
                @unlink($path);
            }
        }
        if (!empty($removed)) {
            $db = \Kotchasan\DB::create();
            $db->delete('dms_files', [['id', $removed]], 0);
            $db->delete('dms_download', [['dms_id', (int) $dms_id], ['file_id', $removed]], 0);
        }

        return $removed;
    }
}
