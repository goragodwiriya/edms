<?php
/**
 * @filesource modules/dms/views/view.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\View;

use Kotchasan\Date;

/**
 * แสดงรายละเอียดของเอกสาร (modal)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงฟอร์ม Modal สำหรับแสดงรายละเอียดของเอกสาร
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $content = '';
        $content .= '<article class=modal_detail>';
        $content .= '<header><h3 class=icon-file>{LNG_Details of} {LNG_Document}</h3></header>';
        $content .= '<div class="table fullwidth">';
        $content .= '<p class=tr><span class="td item icon-number">{LNG_Document No.}</span><span class="td item center">&nbsp;:&nbsp;</span><span class="td item">'.$index->document_no.'</span></p>';
        $content .= '<p class=tr><span class="td item icon-file">{LNG_Document title}</span><span class="td item center">&nbsp;:&nbsp;</span><span class="td item">'.$index->topic.'</span></p>';
        $content .= '<p class=tr><span class="td item icon-calendar">{LNG_Date}</span><span class="td item center">&nbsp;:&nbsp;</span><span class="td item">'.Date::format($index->create_date, 'd M Y').'</span></p>';
        $content .= '<p class=tr><span class="td item icon-edit top">{LNG_Detail}</span><span class="td item center top">&nbsp;:&nbsp;</span><span class="td item top">'.nl2br($index->detail).'</span></p>';
        if ($index->url != '') {
            $content .= '<p class=tr><span class="td item icon-world top">{LNG_URL}</span><span class="td item top">:</span><span class="td item top"><a href="'.$index->url.'" target=_blank>'.$index->url.'</a></span></p>';
        }
        $content .= '</div>';
        if ($index->url == '') {
            foreach (\Dms\View\Model::files($index->id, $login) as $item) {
                $img = '<img src="'.(is_file(ROOT_PATH.'skin/ext/'.$item->ext.'.png') ? WEB_URL.'skin/ext/'.$item->ext.'.png' : WEB_URL.'skin/ext/file.png').'" alt="'.$item->ext.'">';
                $c = empty($item->downloads) ? 'silver' : 'green';
                $content .= '<p class="item"><span class="icon-valid color-'.$c.' notext"></span>'.$img.' '.$item->topic.'.'.$item->ext.'</p>';
            }
        }
        $content .= '</article>';
        return $content;
    }
}
