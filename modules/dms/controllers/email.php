<?php
/**
 * @filesource modules/dms/controllers/email.php
 *
 * แจ้งเตือนเอกสารใหม่ทางอีเมล LINE และ Telegram ถึงผู้ดูแลระบบและผู้ที่มีสิทธิ์ can_manage_dms
 * (เหมือน modules/dms/models/email.php ของระบบเดิม)
 */

namespace Dms\Email;

use Kotchasan\Language;

class Controller extends \Kotchasan\KBase
{
    /**
     * ส่งแจ้งเตือน คืนข้อความผลการส่ง
     *
     * @param array $document ต้องมี topic
     *
     * @return string
     */
    public static function send(array $document)
    {
        $lines = [];
        $emails = [];
        $telegrams = [];
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'username', 'name', 'line_uid', 'telegram_id')
            ->from('user')
            ->where(['active', 1])
            ->where([
                ['status', 1],
                ['permission', 'LIKE', '%,can_manage_dms,%']
            ], 'OR');
        if (!empty(self::$cfg->demo_mode)) {
            // บัญชีตัวอย่าง (เข้าระบบด้วยโซเชียล) ไม่ได้รับแจ้งเตือน
            $query->where(['social', 'user']);
        }
        foreach ($query->fetchAll() as $item) {
            if (!empty($item->username)) {
                $emails[] = $item->name.'<'.$item->username.'>';
            }
            if (!empty($item->line_uid)) {
                $lines[] = $item->line_uid;
            }
            if (!empty($item->telegram_id)) {
                $telegrams[$item->telegram_id] = $item->telegram_id;
            }
        }
        $msg = Language::trans(implode("\n", [
            '{LNG_Document management system}',
            '{LNG_New document} : '.$document['topic'],
            'URL : '.WEB_URL.'dms'
        ]));
        $ret = [];
        if (!empty(self::$cfg->telegram_bot_token) && !empty($telegrams)) {
            $err = \Gcms\Telegram::sendTo(array_values($telegrams), $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty(self::$cfg->line_channel_access_token) && !empty($lines)) {
            $err = \Gcms\Line::sendTo($lines, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty(self::$cfg->noreply_email) && !empty($emails)) {
            $subject = '['.self::$cfg->web_title.'] '.Language::get('Document management system');
            $html = nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
            foreach ($emails as $item) {
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $html);
                if ($err->error()) {
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            return empty($ret) ? 'Your message was sent successfully' : implode("\n", array_unique($ret));
        }

        return 'Saved successfully';
    }
}
