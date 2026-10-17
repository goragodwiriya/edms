<?php
/**
 * @filesource modules/dms/models/write.php
 */

namespace Dms\Write;

use Dms\Helper\Controller as Helper;

class Model extends \Kotchasan\Model
{
    /**
     * เอกสารสำหรับฟอร์ม · $id = 0 คือเอกสารใหม่ · ไม่พบคืน null
     *
     * @param int $id
     * @param object $login
     *
     * @return object|null
     */
    public static function get($id, $login)
    {
        if ($id <= 0) {
            return (object) [
                'id' => 0,
                'member_id' => (int) $login->id,
                'document_no' => '',
                'create_date' => date('Y-m-d'),
                'topic' => '',
                'detail' => '',
                'url' => '',
                'want' => 'file',
                // เอกสารใหม่ส่งถึงแผนกของผู้อัปโหลดเป็นค่าเริ่มต้น (เหมือนระบบเดิม)
                'department' => Helper::departments($login),
                'cabinet' => ''
            ];
        }
        $document = static::createQuery()
            ->select('id', 'member_id', 'document_no', 'create_date', 'topic', 'detail', 'url')
            ->from('dms')
            ->where(['id', $id])
            ->first();
        if (!$document) {
            return null;
        }
        $document->id = (int) $document->id;
        $document->member_id = (int) $document->member_id;
        $document->want = $document->url === '' ? 'file' : 'url';
        $document->department = [];
        $document->cabinet = '';
        foreach (self::meta($document->id) as $item) {
            if ($item->type === 'department') {
                $document->department[] = $item->value;
            } else {
                $document->{$item->type} = $item->value;
            }
        }

        return $document;
    }

    /**
     * หมวดหมู่ของเอกสาร
     *
     * @param int $id
     *
     * @return array
     */
    public static function meta($id)
    {
        return static::createQuery()
            ->select('type', 'value')
            ->from('dms_meta')
            ->where(['dms_id', $id])
            ->fetchAll();
    }

    /**
     * เลขที่เอกสารนี้ถูกใช้กับเอกสารอื่นแล้วหรือไม่
     *
     * @param string $document_no
     * @param int $id เอกสารที่กำลังแก้ไข (0 = ใหม่)
     *
     * @return bool
     */
    public static function documentNoExists($document_no, $id)
    {
        $search = static::createQuery()
            ->select('id')
            ->from('dms')
            ->where([
                ['document_no', $document_no],
                ['id', '!=', (int) $id]
            ])
            ->first();

        return (bool) $search;
    }

    /**
     * บันทึกเอกสาร หมวดหมู่ และไฟล์ที่อัปโหลดแล้ว คืนค่า id ของเอกสาร
     *
     * $files เป็นไฟล์ที่ย้ายเข้า datas/dms/<id>/ แล้ว (ไม่มี dms_id)
     * เอกสารแบบ URL ลบไฟล์เดิมทั้งหมดของเอกสาร (เหมือนระบบเดิม) พร้อมประวัติการดาวน์โหลดไฟล์เหล่านั้น
     *
     * @param int $id
     * @param array $save
     * @param array $meta type => value|array
     *
     * @return int
     */
    public static function save($id, array $save, array $meta)
    {
        $db = \Kotchasan\DB::create();
        if ($id === 0) {
            $id = (int) $db->insert('dms', $save);
        } else {
            $db->update('dms', [['id', $id]], $save);
        }
        if ($save['url'] !== '') {
            self::removeFiles($id);
        }
        $db->delete('dms_meta', [['dms_id', $id]], 0);
        foreach ($meta as $type => $values) {
            foreach ((array) $values as $value) {
                $db->insert('dms_meta', [
                    'dms_id' => $id,
                    'type' => $type,
                    'value' => (string) $value
                ]);
            }
        }

        return $id;
    }

    /**
     * บันทึกไฟล์ที่ย้ายเข้าโฟลเดอร์ของเอกสารแล้ว
     *
     * @param int $id
     * @param array $files
     */
    public static function addFiles($id, array $files)
    {
        $db = \Kotchasan\DB::create();
        foreach ($files as $file) {
            $file['dms_id'] = $id;
            $db->insert('dms_files', $file);
        }
    }

    /**
     * ลบไฟล์ทั้งหมดของเอกสาร (ทั้งในฐานข้อมูลและบนดิสก์) พร้อมประวัติการดาวน์โหลดไฟล์
     * ประวัติการเปิดลิงก์ (file_id = 0) เก็บไว้
     *
     * @param int $id
     */
    public static function removeFiles($id)
    {
        $db = \Kotchasan\DB::create();
        $db->delete('dms_files', [['dms_id', $id]], 0);
        $db->delete('dms_download', [['dms_id', $id], ['file_id', '>', 0]], 0);
        Helper::removeDocumentDir($id);
    }
}
