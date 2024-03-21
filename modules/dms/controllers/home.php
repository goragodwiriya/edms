<?php
/**
 * @filesource modules/dms/controllers/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Home;

use Kotchasan\Http\Request;

/**
 * Controller สำหรับการแสดงผลหน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นสร้าง card
     *
     * @param Request               $request
     * @param \Kotchasan\Collection $card
     * @param array                 $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        if ($login) {
            $from = date('Y-m-d', strtotime('-30 days'));
            $to = date('Y-m-d');
            \Index\Home\Controller::renderCard($card, 'icon-edocument', '{LNG_New document}', number_format(\Dms\Home\Model::getNew($login, $from, $to)), '30 {LNG_days}', 'index.php?module=dms&amp;from='.$from.'&amp;to='.$to);
        }
    }
}
