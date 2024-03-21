<?php
/**
 * @filesource modules/dms/views/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Settings;

use Kotchasan\Html;
use Kotchasan\Http\UploadedFile;
use Kotchasan\Language;
use Kotchasan\Text;

/**
 * module=dms-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ตั้งค่าโมดูล
     *
     * @return string
     */
    public function render()
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/dms/model/settings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-config',
            'title' => '{LNG_Module settings}'
        ));
        $comment = '{LNG_Prefix, if changed The number will be counted again. You can enter %Y%M (year, month).}';
        $comment .= ', {LNG_Number such as %04d (%04d means 4 digits, maximum 11 digits)}';
        $groups = $fieldset->add('groups', array(
            'comment' => $comment
        ));
        // dms_prefix
        $groups->add('text', array(
            'id' => 'dms_prefix',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Prefix}',
            'placeholder' => 'DOC%Y%M-',
            'value' => isset(self::$cfg->dms_prefix) ? self::$cfg->dms_prefix : ''
        ));
        // dms_format_no
        $groups->add('text', array(
            'id' => 'dms_format_no',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Document No.}',
            'placeholder' => '%04d, DOC%Y%M-%04d',
            'value' => isset(self::$cfg->dms_format_no) ? self::$cfg->dms_format_no : 'DOC%Y%M-%04d'
        ));
        // dms_user_permission
        $fieldset->add('checkboxgroups', array(
            'id' => 'dms_user_permission',
            'labelClass' => 'g-input icon-list',
            'itemClass' => 'item',
            'label' => '{LNG_Permission}',
            'comment' => '{LNG_Default license When member registration}',
            'options' => \Dms\Init\Controller::updatePermissions([]),
            'value' => self::$cfg->dms_user_permission
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-upload',
            'title' => '{LNG_Upload}'
        ));
        // dms_require_attach_file
        $fieldset->add('checkbox', array(
            'id' => 'dms_require_attach_file',
            'itemClass' => 'item',
            'label' => '{LNG_There is no need to attach files}',
            'value' => 1,
            'checked' => !empty(self::$cfg->dms_require_attach_file)
        ));
        // dms_upload_options
        $fieldset->add('select', array(
            'id' => 'dms_upload_options',
            'labelClass' => 'g-input icon-menus',
            'itemClass' => 'item',
            'label' => '{LNG_Upload}',
            'options' => Language::get('DMS_UPLOAD_OPTIONS'),
            'value' => self::$cfg->dms_upload_options
        ));
        // dms_file_typies
        $fieldset->add('text', array(
            'id' => 'dms_file_typies',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Type of file uploads}',
            'comment' => '{LNG_Specify the file extension that allows uploading. English lowercase letters and numbers 2-4 characters to separate each type with a comma (,) and without spaces. eg zip,rar,doc,docx}',
            'value' => implode(',', self::$cfg->dms_file_typies)
        ));
        // อ่านการตั้งค่าขนาดของไฟลอัปโหลด
        $upload_max = UploadedFile::getUploadSize(true);
        // dms_upload_size
        $sizes = [];
        foreach (array(1, 2, 4, 6, 8, 16, 32, 64, 128, 256, 512, 1024, 2048) as $i) {
            $a = $i * 1048576;
            if ($a <= $upload_max) {
                $sizes[$a] = Text::formatFileSize($a);
            }
        }
        if (!isset($sizes[$upload_max])) {
            $sizes[$upload_max] = Text::formatFileSize($upload_max);
        }
        // dms_upload_size
        $fieldset->add('select', array(
            'id' => 'dms_upload_size',
            'labelClass' => 'g-input icon-upload',
            'itemClass' => 'item',
            'label' => '{LNG_Size of the file upload}',
            'comment' => '{LNG_The size of the files can be uploaded. (Should not exceed the value of the Server :upload_max_filesize.)}',
            'options' => $sizes,
            'value' => self::$cfg->dms_upload_size
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-download',
            'title' => '{LNG_Download}'
        ));
        // dms_download_action
        $fieldset->add('select', array(
            'id' => 'dms_download_action',
            'labelClass' => 'g-input icon-download',
            'itemClass' => 'item',
            'label' => '{LNG_When download}',
            'options' => Language::get('DOWNLOAD_ACTIONS'),
            'value' => self::$cfg->dms_download_action
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        \Gcms\Controller::$view->setContentsAfter(array(
            '/:upload_max_filesize/' => Text::formatFileSize($upload_max)
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
