<?php
/**
 * @filesource modules/dms/models/download.php
 */

namespace Dms\Download;

use Dms\Helper\Controller as Helper;

class Model extends \Kotchasan\Model
{
    /**
     * ไฟล์พร้อมข้อมูลเอกสารที่เป็นเจ้าของ ไม่พบคืน null
     *
     * @param int $id dms_files.id
     *
     * @return object|null
     */
    public static function getFile($id)
    {
        if ($id <= 0) {
            return null;
        }
        $file = static::createQuery()
            ->select('F.id', 'F.dms_id', 'F.name', 'F.ext', 'F.size', 'F.file', 'A.member_id', 'A.url')
            ->from('dms_files F')
            ->join('dms A', [['A.id', 'F.dms_id']], 'INNER')
            ->where(['F.id', $id])
            ->first();

        return $file ?: null;
    }

    /**
     * เอกสาร ไม่พบคืน null
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getDocument($id)
    {
        if ($id <= 0) {
            return null;
        }
        $document = static::createQuery()
            ->select('id', 'member_id', 'document_no', 'topic', 'url')
            ->from('dms')
            ->where(['id', $id])
            ->first();

        return $document ?: null;
    }

    /**
     * ผู้ใช้เห็นเอกสารนี้ในรายการดาวน์โหลดหรือไม่ — กติกาเดียวกับ Dms\Documents\Model
     * (สมาชิกที่ไม่มีแผนกเห็นทุกเอกสาร)
     *
     * @param int $dms_id
     * @param object $login
     *
     * @return bool
     */
    public static function isVisible($dms_id, $login)
    {
        $departments = Helper::departments($login);
        if (empty($departments)) {
            return true;
        }
        $found = static::createQuery()
            ->select('dms_id')
            ->from('dms_meta')
            ->where([
                ['dms_id', (int) $dms_id],
                ['type', 'department'],
                ['value', $departments]
            ])
            ->first();

        return (bool) $found;
    }

    /**
     * นับการดาวน์โหลด 1 ครั้ง (1 แถวต่อ ไฟล์ x สมาชิก) · file_id = 0 คือการเปิดลิงก์ของเอกสาร
     *
     * @param int $dms_id
     * @param int $file_id
     * @param int $member_id
     *
     * @return int จำนวนครั้งล่าสุด
     */
    public static function count($dms_id, $file_id, $member_id)
    {
        $db = \Kotchasan\DB::create();
        $where = [
            ['dms_id', (int) $dms_id],
            ['file_id', (int) $file_id],
            ['member_id', (int) $member_id]
        ];
        $download = $db->first('dms_download', $where);
        $now = date('Y-m-d H:i:s');
        if ($download) {
            $downloads = (int) $download->downloads + 1;
            $db->update('dms_download', [['id', $download->id]], [
                'downloads' => $downloads,
                'updated_at' => $now
            ]);
        } else {
            $downloads = 1;
            $db->insert('dms_download', [
                'dms_id' => (int) $dms_id,
                'file_id' => (int) $file_id,
                'member_id' => (int) $member_id,
                'downloads' => $downloads,
                'updated_at' => $now
            ]);
        }

        return $downloads;
    }

    /**
     * ข้อมูลที่ใช้ส่งไฟล์ออก ไม่พบไฟล์จริงคืน null
     *
     * ⚠️ ตรวจว่าไฟล์อยู่ใต้ datas/dms/ จริง — คอลัมน์ file เป็นข้อความในฐานข้อมูล
     * ห้ามเชื่อว่าชี้ไปที่ปลอดภัยเสมอ
     *
     * @param object $file
     *
     * @return array|null
     */
    public static function prepare($file)
    {
        $base = realpath(ROOT_PATH.DATA_FOLDER.'dms');
        $path = realpath(ROOT_PATH.DATA_FOLDER.$file->file);
        if ($base === false || $path === false || strpos($path, $base.DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            return null;
        }
        $ext = strtolower((string) $file->ext);
        $inline = !empty(self::$cfg->dms_download_action)
            && in_array($ext, (array) self::$cfg->know_file_typies, true);
        $mime = \Kotchasan\Mime::get($ext);

        return [
            'path' => $path,
            'name' => $file->name.($ext === '' ? '' : '.'.$ext),
            'mime' => $mime ?: 'application/octet-stream',
            'size' => filesize($path),
            'inline' => $inline
        ];
    }
}
