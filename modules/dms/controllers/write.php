<?php
/**
 * @filesource modules/dms/controllers/write.php
 *
 * GET  api/dms/write/get?id=   ข้อมูลฟอร์มเอกสาร (id = 0 คือเอกสารใหม่)  (เดิม module=dms-write)
 * POST api/dms/write/save      บันทึกเอกสาร + อัปโหลดไฟล์ + แจ้งเตือน
 */

namespace Dms\Write;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
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
            if (!Helper::canUpload($login)) {
                return $this->errorResponse('Permission required', 403);
            }
            $document = Model::get($request->get('id')->toInt(), $login);
            if (!$document) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }
            if ($document->id > 0 && !Helper::canEditDocument($login, $document)) {
                return $this->errorResponse('Permission required', 403);
            }
            $category = Helper::category();
            // ชื่อของตู้เก็บเอกสาร (ช่องพิมพ์ได้ + เลือกจากรายการ)
            $document->cabinet_text = $category->get('cabinet', $document->cabinet);
            $document->title = Language::get($document->id === 0 ? 'Upload' : 'Edit').' '.Language::get('Document');
            $document->file_comment = Language::trans('{LNG_Upload :type files} {LNG_no larger than :size} ({LNG_Can select multiple files})');
            $document->file_comment = strtr($document->file_comment, [
                ':type' => implode(', ', (array) self::$cfg->dms_file_typies),
                ':size' => Text::formatFileSize((int) self::$cfg->dms_upload_size)
            ]);
            $document->accept = '.'.implode(',.', (array) self::$cfg->dms_file_typies);

            return $this->successResponse([
                'data' => $document,
                'options' => [
                    'department' => Helper::departmentOptions($login),
                    'cabinet' => $category->toOptions('cabinet'),
                    'want' => \Gcms\Controller::arrayToOptions(Language::get('DMS_WANT'))
                ]
            ], 'Document loaded');
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
            if (!ApiController::canModify($login, ['can_upload_dms'])) {
                return $this->errorResponse('Permission required', 403);
            }
            $id = $request->post('id')->toInt();
            $index = Model::get($id, $login);
            if (!$index) {
                return $this->errorResponse('No data available', 404);
            }
            if ($index->id > 0 && !Helper::canEditDocument($login, $index)) {
                return $this->errorResponse('Permission required', 403);
            }
            $save = [
                'document_no' => $request->post('document_no')->topic(),
                'create_date' => $request->post('create_date')->date(),
                'topic' => $request->post('topic')->topic(),
                'detail' => $request->post('detail')->textarea(),
                'url' => $request->post('url')->url()
            ];
            $want = $request->post('want')->toString() === 'url' ? 'url' : 'file';
            $errors = [];
            if ($save['document_no'] !== '' && Model::documentNoExists($save['document_no'], $index->id)) {
                $errors['document_no'] = Language::replace('This :name already exist', [':name' => Language::get('Document No.')]);
            }
            if ($save['create_date'] === '') {
                $errors['create_date'] = 'Please fill in';
            }
            if ($save['topic'] === '') {
                $errors['topic'] = 'Please fill in';
            }
            // แผนก — รับเฉพาะแผนกที่ผู้ใช้เลือกได้จริง (ระบบเดิมเชื่อค่าที่ส่งมาทั้งหมด)
            $allowed = array_map('strval', array_column(Helper::departmentOptions($login), 'value'));
            $departments = [];
            if (!empty($allowed)) {
                foreach ((array) $request->post('department', [])->topic() as $value) {
                    if (in_array((string) $value, $allowed, true) && !in_array((string) $value, $departments, true)) {
                        $departments[] = (string) $value;
                    }
                }
                if (empty($departments)) {
                    $errors['department'] = 'Please select';
                }
            }
            // ตู้เก็บเอกสาร — พิมพ์ชื่อใหม่ได้ ระบบสร้างให้ตอนบันทึก
            $cabinet = $request->post('cabinet_text')->topic();
            if ($cabinet === '') {
                $errors['cabinet'] = 'Please fill in';
            }
            $uploads = [];
            if ($want === 'file') {
                $uploads = $this->uploadedFiles($request, $errors);
                if ($index->id === 0 && empty(self::$cfg->dms_require_attach_file) && empty($uploads) && !isset($errors['file'])) {
                    $errors['file'] = 'Please browse file';
                }
                $save['url'] = '';
            } elseif ($save['url'] === '') {
                $errors['url'] = 'Please fill in';
            } elseif (!preg_match('/^https?:\/\/.+$/i', $save['url'])) {
                $errors['url'] = 'URL must begin with http:// or https://';
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }
            if ($save['document_no'] === '') {
                $save['document_no'] = \Index\Number\Model::get(0, (string) self::$cfg->dms_format_no, 'dms', 'document_no', (string) self::$cfg->dms_prefix);
            }
            $meta = [
                'department' => $departments,
                'cabinet' => \Dms\Category\Controller::save('cabinet', $cabinet)
            ];
            if ($index->id === 0) {
                $save['member_id'] = (int) $login->id;
            }
            $id = Model::save($index->id, $save, $meta);
            if (!empty($uploads)) {
                $result = $this->moveFiles($id, $uploads);
                if ($result['error'] !== '') {
                    return $this->formErrorResponse(['file' => $result['error']], 400);
                }
                Model::addFiles($id, $result['files']);
            }
            \Index\Log\Model::add($id, 'dms', 'Save', '{LNG_Document} ID : '.$id, $login->id);
            // แจ้งเตือนทุกครั้งที่บันทึก (เหมือนระบบเดิม) ข้อความคืนเป็นผลการส่ง
            $message = \Dms\Email\Controller::send($save);

            return $this->redirectResponse('/dms-setup', $message, 200, 1000);
        } catch (\Kotchasan\ApiException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ไฟล์ที่อัปโหลดมาในช่อง file[] ที่ผ่านการตรวจแล้ว (ยังไม่ย้าย)
     * ข้อผิดพลาดใส่ใน $errors['file']
     *
     * @param Request $request
     * @param array $errors
     *
     * @return \Kotchasan\Http\UploadedFile[]
     */
    protected function uploadedFiles(Request $request, array &$errors)
    {
        $files = [];
        $typies = array_map('strtolower', (array) self::$cfg->dms_file_typies);
        $maxSize = (int) self::$cfg->dms_upload_size;
        foreach ($request->getUploadedFiles() as $name => $file) {
            /* @var $file \Kotchasan\Http\UploadedFile */
            if (!preg_match('/^file(\[[0-9]*\])?$/', $name)) {
                continue;
            }
            if ($file->hasUploadFile()) {
                if (!$file->validFileExt($typies)) {
                    $errors['file'] = Language::get('The type of file is invalid');
                } elseif ($maxSize > 0 && $file->getSize() > $maxSize) {
                    $errors['file'] = Language::get('The file size larger than the limit');
                } else {
                    $files[] = $file;
                }
            } elseif ($file->hasError()) {
                $errors['file'] = Language::get($file->getErrorMessage());
            }
        }

        return $files;
    }

    /**
     * ย้ายไฟล์เข้า datas/dms/<id>/ ด้วยชื่อสุ่ม
     *
     * @param int $id
     * @param \Kotchasan\Http\UploadedFile[] $uploads
     *
     * @return array ['files' => แถวของ dms_files, 'error' => ข้อความผิดพลาด]
     */
    protected function moveFiles($id, array $uploads)
    {
        $dir = Helper::documentDir($id);
        if ($dir === false) {
            return ['files' => [], 'error' => Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'dms/'.$id.'/')];
        }
        $files = [];
        $now = date('Y-m-d H:i:s');
        foreach ($uploads as $file) {
            $ext = strtolower($file->getClientFileExt());
            do {
                $name = uniqid().'.'.$ext;
            } while (file_exists($dir.$name));
            try {
                $file->moveTo($dir.$name);
            } catch (\Exception $e) {
                return ['files' => $files, 'error' => Language::get($e->getMessage())];
            }
            $topic = preg_replace('/\.'.preg_quote($ext, '/').'$/i', '', $file->getClientFilename());
            $topic = mb_substr($topic, 0, 150);
            $files[] = [
                'topic' => $topic,
                'name' => preg_replace('/[,;:_\-]{1,}/', '_', $topic),
                'ext' => $ext,
                'size' => (int) $file->getSize(),
                'file' => 'dms/'.$id.'/'.$name,
                'created_at' => $now
            ];
        }

        return ['files' => $files, 'error' => ''];
    }
}
