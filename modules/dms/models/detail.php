<?php
/**
 * @filesource modules/dms/models/detail.php
 */

namespace Dms\Detail;

use Dms\Helper\Controller as Helper;
use Kotchasan\Database\Sql;

class Model extends \Kotchasan\Model
{
    /**
     * เอกสารที่จะแสดงใน modal ไม่พบคืน null
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id <= 0) {
            return null;
        }
        $document = static::createQuery()
            ->select('id', 'document_no', 'topic', 'member_id', 'create_date', 'detail', 'url')
            ->from('dms')
            ->where(['id', $id])
            ->first();

        return $document ?: null;
    }

    /**
     * ไฟล์ของเอกสาร พร้อมสถานะว่าผู้ใช้คนนี้เคยดาวน์โหลดแล้วหรือยัง
     *
     * @param int $id
     * @param object $login
     *
     * @return array
     */
    public static function files($id, $login)
    {
        $result = [];
        $query = static::createQuery()
            ->select('F.id', 'F.topic', 'F.ext', Sql::IFNULL('W.downloads', 0, 'downloads'))
            ->from('dms_files F')
            ->join('dms_download W', [
                ['W.dms_id', 'F.dms_id'],
                ['W.file_id', 'F.id'],
                ['W.member_id', (int) $login->id]
            ], 'LEFT')
            ->where(['F.dms_id', $id])
            ->orderBy('F.id');
        foreach ($query->fetchAll() as $item) {
            $result[] = [
                'id' => (int) $item->id,
                'name' => $item->topic.'.'.$item->ext,
                'icon' => Helper::extIcon($item->ext),
                'downloaded' => (int) $item->downloads > 0 ? 1 : 0
            ];
        }

        return $result;
    }
}
