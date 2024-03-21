<?php
/**
 * @filesource modules/dms/models/email.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Email;

use Kotchasan\Language;

/**
 * ส่งอีเมลและ LINE ไปยังผู้ที่เกี่ยวข้อง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ส่งอีเมลและ LINE แจ้งการทำรายการ
     *
     * @param array $order
     *
     * @return string
     */
    public static function send($order)
    {
        $lines = [];
        $emails = [];
        // ตรวจสอบรายชื่อผู้รับ
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'username', 'name', 'line_uid')
            ->from('user')
            ->where(array('active', 1))
            ->andWhere(array(
                array('status', 1),
                array('permission', 'LIKE', '%,can_manage_dms,%')
            ), 'OR')
            ->cacheOn();
        if (self::$cfg->demo_mode) {
            $query->andWhere(array('social', 0));
        }
        foreach ($query->execute() as $item) {
            // เจ้าหน้าที่
            $emails[] = $item->name.'<'.$item->username.'>';
            if ($item->line_uid != '') {
                $lines[] = $item->line_uid;
            }
        }
        // ข้อความ
        $msg = array(
            '{LNG_Document management system}',
            '{LNG_New document} : '.$order['topic'],
            'URL : '.WEB_URL.'index.php?module=dms-write&id='.$order['id']
        );
        // ข้อความ
        $msg = Language::trans(implode("\n", $msg));
        // ส่งข้อความ
        $ret = [];
        if (!empty(self::$cfg->line_api_key)) {
            // ส่ง LINE
            $err = \Gcms\Line::send($msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        // LINE ส่วนตัว
        if (!empty($lines)) {
            $err = \Gcms\Line::sendTo($lines, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (self::$cfg->noreply_email != '') {
            // หัวข้ออีเมล
            $subject = '['.self::$cfg->web_title.'] '.Language::get('Document management system');
            // รายละเอียดในอีเมล (แอดมิน)
            $msg = nl2br($msg);
            foreach ($emails as $item) {
                // ส่งอีเมล
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $msg);
                if ($err->error()) {
                    // คืนค่า error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            // ส่งอีเมลสำเร็จ หรือ error การส่งเมล
            return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
        } else {
            // ไม่มีอีเมลต้องส่ง
            return Language::get('Saved successfully');
        }
    }
}
