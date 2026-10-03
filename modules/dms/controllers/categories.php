<?php
/**
 * @filesource modules/dms/controllers/categories.php
 *
 * หน้าแก้ไขตู้เก็บเอกสาร — ใช้ Model และหน้าตาเดียวกับ Index\Categories แต่ตรวจสิทธิ์
 * can_manage_dms ตามระบบเดิม (Index\Categories บังคับ can_config)
 */

namespace Dms\Categories;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Index\Categories\Model;
use Kotchasan\Http\Request;
use Kotchasan\Text;

class Controller extends \Index\Categories\Controller
{
    /**
     * ประเภทหมวดหมู่ที่แก้ไขได้จากหน้านี้ (แผนกเป็นของแกน แก้ที่ ตั้งค่า > แผนก)
     */
    const TYPES = [
        'cabinet' => '{LNG_Cabinet}'
    ];

    /**
     * @var array
     */
    protected $categories = self::TYPES;

    /**
     * ประเภทหมวดหมู่ของหน้านี้ (ใช้สร้างเมนู)
     *
     * @return array
     */
    public static function items()
    {
        return self::TYPES;
    }

    /**
     * GET api/dms/categories/get?type=
     *
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
            if (!Helper::canManage($login)) {
                return $this->errorResponse('Forbidden', 403);
            }
            $type = $request->get('type')->filter('a-z_');
            if (!isset(self::TYPES[$type])) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => [
                    'type' => $type,
                    'title' => self::TYPES[$type],
                    'options' => [
                        'columns' => Model::getColumns($this->multiLanguage),
                        'data' => Model::get($type)
                    ]
                ]
            ], 'Category details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/dms/categories/save
     *
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
            if (!ApiController::canModify($login, ['can_manage_dms'])) {
                return $this->errorResponse('Permission required', 403);
            }
            $type = $request->post('type')->filter('a-z_');
            if (!isset(self::TYPES[$type])) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }
            $ids = $request->post('id', [])->topic();
            $languages = \Index\Language\Model::getLanguages();
            $topics = [];
            foreach ($languages as $lng) {
                $topics[$lng] = $request->post($lng, [])->topic();
            }
            $errors = [];
            $save = [];
            foreach ($ids as $key => $id) {
                $category_id = Text::topic($id);
                if ($category_id === '') {
                    $errors['category_id_'.$key] = 'Category ID is required';
                    continue;
                }
                $filled = false;
                foreach ($languages as $lng) {
                    $topic = isset($topics[$lng][$key]) ? Text::topic($topics[$lng][$key]) : '';
                    if ($topic === '') {
                        continue;
                    }
                    $filled = true;
                    if (isset($save[$category_id.$lng])) {
                        $errors['category_id_'.$key] = 'Category ID '.$category_id.' already exists';
                    } else {
                        $save[$category_id.$lng] = [
                            'type' => $type,
                            'category_id' => $category_id,
                            'language' => $lng,
                            'topic' => $topic
                        ];
                    }
                }
                if (!$filled) {
                    $errors['category_'.$languages[0].'_'.$key] = 'Please fill in';
                }
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }
            if (!$this->multiLanguage) {
                // หมวดหมู่ภาษาเดียว เก็บแถวเดียวต่อรหัส
                $unique = [];
                foreach ($save as $item) {
                    if (!isset($unique[$item['category_id']])) {
                        $item['language'] = '';
                        $unique[$item['category_id']] = $item;
                    }
                }
                $save = $unique;
            }
            Model::save($type, $save, $this->multiLanguage);
            \Index\Log\Model::add(0, 'dms', 'Save', '{LNG_Save} '.self::TYPES[$type], $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Kotchasan\ApiException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
