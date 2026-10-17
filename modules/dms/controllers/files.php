<?php
/**
 * @filesource modules/dms/controllers/files.php
 *
 * GET  api/dms/files?dms_id=   ไฟล์ของเอกสาร (เดิม module=dms-files)
 * POST api/dms/files/action    delete
 */

namespace Dms\Files;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends \Gcms\Table
{
    /**
     * @var array
     */
    protected $allowedSortColumns = ['topic', 'size', 'created_at'];

    /**
     * เอกสารที่กำลังดู (อ่านใน checkAuthorization)
     *
     * @var object|null
     */
    protected $document = null;

    /**
     * @param Request $request
     * @param object $login
     *
     * @return true|\Kotchasan\Http\Response
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!Helper::canUpload($login)) {
            return $this->errorResponse('Permission required', 403);
        }
        $this->document = \Dms\Download\Model::getDocument($request->get('dms_id')->toInt());
        if (!$this->document) {
            return $this->errorResponse('No data available', 404);
        }
        if (!Helper::canEditDocument($login, $this->document)) {
            return $this->errorResponse('Permission required', 403);
        }

        return true;
    }

    /**
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'id' => $this->document ? (int) $this->document->id : 0
        ];
    }

    /**
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params);
    }

    /**
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->icon = Helper::extIcon($item->ext);
            $item->size = (int) $item->size;
        }

        return $datas;
    }

    /**
     * ข้อมูลเอกสารสำหรับหัวหน้า
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getOptions(array $params, $login)
    {
        return [
            'document' => $this->document ? [
                'id' => (int) $this->document->id,
                'document_no' => $this->document->document_no,
                'topic' => $this->document->topic
            ] : null
        ];
    }

    /**
     * ลบไฟล์ที่เลือก — เฉพาะไฟล์ของเอกสารที่ผู้ใช้มีสิทธิ์แก้ไข
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!Helper::canUpload($login) || !ApiController::isNotDemoMode($login)) {
            return $this->errorResponse('Permission required', 403);
        }
        $byDocument = [];
        foreach ($request->request('ids', [])->toInt() as $id) {
            $file = \Dms\Download\Model::getFile($id);
            if ($file && Helper::canEditDocument($login, $file)) {
                $byDocument[(int) $file->dms_id][] = (int) $file->id;
            }
        }
        $removed = [];
        foreach ($byDocument as $dms_id => $ids) {
            $removed = array_merge($removed, Model::remove($dms_id, $ids));
        }
        if (empty($removed)) {
            return $this->errorResponse('Unable to complete the transaction', 400);
        }
        \Index\Log\Model::add(0, 'dms', 'Delete', '{LNG_Delete} {LNG_File} ID : '.implode(', ', $removed), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }
}
