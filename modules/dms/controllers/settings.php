<?php
/**
 * @filesource modules/dms/controllers/settings.php
 *
 * GET  api/dms/settings/get   (เดิม module=dms-settings)
 * POST api/dms/settings/save
 */

namespace Dms\Settings;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\Http\Request;
use Kotchasan\Http\UploadedFile;
use Kotchasan\Language;
use Kotchasan\Text;

class Controller extends ApiController
{
    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_config')) {
                return $this->errorResponse('Forbidden', 403);
            }
            $uploadMax = UploadedFile::getUploadSize(true);

            return $this->successResponse([
                'data' => (object) [
                    'dms_prefix' => (string) self::$cfg->dms_prefix,
                    'dms_format_no' => (string) self::$cfg->dms_format_no,
                    'dms_user_permission' => self::defaultPermissions(),
                    'dms_require_attach_file' => !empty(self::$cfg->dms_require_attach_file),
                    'dms_upload_options' => (int) self::$cfg->dms_upload_options,
                    'dms_file_typies' => implode(',', (array) self::$cfg->dms_file_typies),
                    'dms_upload_size' => (int) self::$cfg->dms_upload_size,
                    'dms_download_action' => (int) self::$cfg->dms_download_action,
                    'upload_size_comment' => str_replace(
                        ':upload_max_filesize',
                        Text::formatFileSize($uploadMax),
                        Language::get('The size of the files can be uploaded. (Should not exceed the value of the Server :upload_max_filesize.)')
                    )
                ],
                'options' => (object) [
                    'dms_user_permission' => Helper::permissionOptions(),
                    'dms_upload_options' => \Gcms\Controller::arrayToOptions(Language::get('DMS_UPLOAD_OPTIONS')),
                    'dms_upload_size' => self::sizeOptions($uploadMax, (int) self::$cfg->dms_upload_size),
                    'dms_download_action' => \Gcms\Controller::arrayToOptions(Language::get('DOWNLOAD_ACTIONS'))
                ]
            ], 'Settings loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }
            // ชนิดไฟล์ — ตัวอักษรภาษาอังกฤษพิมพ์เล็กและตัวเลข 1-4 ตัว (คอลัมน์ ext เก็บได้ 4 ตัว)
            $typies = [];
            $errors = [];
            foreach (explode(',', strtolower($request->post('dms_file_typies')->filter('a-zA-Z0-9,'))) as $typ) {
                if ($typ === '') {
                    continue;
                }
                if (strlen($typ) > 4) {
                    $errors['dms_file_typies'] = 'Invalid data';
                }
                $typies[$typ] = $typ;
            }
            if (empty($typies)) {
                $errors['dms_file_typies'] = 'Please fill in';
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }
            $config = Config::load(ROOT_PATH.'settings/config.php');
            $config->dms_prefix = $request->post('dms_prefix')->topic();
            $config->dms_format_no = $request->post('dms_format_no')->topic();
            $config->dms_file_typies = array_keys($typies);
            $config->dms_upload_size = max(0, $request->post('dms_upload_size')->toInt());
            $config->dms_download_action = $request->post('dms_download_action')->toInt() === 1 ? 1 : 0;
            $config->dms_upload_options = $request->post('dms_upload_options')->toInt() === 1 ? 1 : 0;
            $config->dms_require_attach_file = $request->post('dms_require_attach_file')->toBoolean();
            // สิทธิ์ตั้งต้นของสมาชิกใหม่ — แก้เฉพาะสิทธิ์ของโมดูลนี้ใน default_user_permissions
            // สิทธิ์ของโมดูลอื่นที่ตั้งไว้คงเดิม
            $selected = array_intersect((array) $request->post('dms_user_permission', [])->filter('a-z_'), array_keys(Helper::PERMISSIONS));
            $current = isset($config->default_user_permissions) && is_array($config->default_user_permissions) ? $config->default_user_permissions : [];
            $config->default_user_permissions = array_values(array_unique(array_merge(
                array_diff($current, array_keys(Helper::PERMISSIONS)),
                $selected
            )));
            if (Config::save($config, ROOT_PATH.'settings/config.php')) {
                \Index\Log\Model::add(0, 'dms', 'Save', '{LNG_Module Settings} {LNG_Document management system}', $login->id);

                return $this->redirectResponse('reload', 'Saved successfully', 200, 1000);
            }

            return $this->errorResponse(Language::replace('File %s cannot be created or is read-only.', 'settings/config.php'), 500);
        } catch (\Kotchasan\ApiException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * สิทธิ์ของโมดูลที่อยู่ในสิทธิ์ตั้งต้นของสมาชิกใหม่
     *
     * @return array
     */
    public static function defaultPermissions()
    {
        $current = isset(self::$cfg->default_user_permissions) ? (array) self::$cfg->default_user_permissions : [];

        return array_values(array_intersect($current, array_keys(Helper::PERMISSIONS)));
    }

    /**
     * ขนาดไฟล์อัปโหลดที่เลือกได้ — ไม่เกินที่เซิร์ฟเวอร์รับได้ (เหมือนระบบเดิม)
     * ค่าที่ตั้งไว้แล้วอยู่ในรายการเสมอ แม้จะไม่ตรงกับตัวเลือกมาตรฐาน
     *
     * @param int $uploadMax
     * @param int $current
     *
     * @return array
     */
    public static function sizeOptions($uploadMax, $current)
    {
        $sizes = [];
        foreach ([1, 2, 4, 6, 8, 16, 32, 64, 128, 256, 512, 1024, 2048] as $i) {
            $size = $i * 1048576;
            if ($size <= $uploadMax) {
                $sizes[$size] = Text::formatFileSize($size);
            }
        }
        if (!isset($sizes[$uploadMax])) {
            $sizes[$uploadMax] = Text::formatFileSize($uploadMax);
        }
        if ($current > 0 && !isset($sizes[$current])) {
            $sizes[$current] = Text::formatFileSize($current);
        }
        ksort($sizes);

        return \Gcms\Controller::arrayToOptions($sizes);
    }
}
