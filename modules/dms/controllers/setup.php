<?php
/**
 * @filesource modules/dms/controllers/setup.php
 *
 * GET  api/dms/setup         รายการเอกสารสำหรับผู้อัปโหลด (เดิม module=dms-setup)
 * POST api/dms/setup/action  delete
 */

namespace Dms\Setup;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends \Gcms\Table
{
    /**
     * @var array
     */
    protected $allowedSortColumns = ['create_date', 'document_no', 'topic'];

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

        return true;
    }

    /**
     * ผู้ที่ถูกจำกัดเฉพาะแผนกตัวเองเห็นเฉพาะเอกสารของแผนกตัวเอง เลือกแผนกอื่นไม่ได้
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        $department = $request->get('department')->topic();
        if (Helper::isUploadRestricted($login)) {
            $own = Helper::departments($login);
            $department = in_array($department, $own, true) ? [$department] : $own;
        }

        return [
            'from' => $request->get('from')->date(),
            'to' => $request->get('to')->date(),
            'department' => $department,
            'cabinet' => $request->get('cabinet')->topic()
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
     * can_edit ใช้ซ่อนปุ่มแก้ไข/ไฟล์ของเอกสารที่ผู้ใช้แก้ไม่ได้
     *
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->is_url = $item->url === '' ? 0 : 1;
            $item->department = (string) $item->department;
            $item->cabinet = (string) $item->cabinet;
            $item->can_edit = Helper::canEditDocument($login, $item) ? 1 : 0;
        }

        return $datas;
    }

    /**
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        $category = Helper::category();

        return [
            'department' => Helper::departmentOptions($login),
            'cabinet' => $category->toOptions('cabinet')
        ];
    }

    /**
     * ลบเอกสารที่เลือก — เฉพาะที่ผู้ใช้มีสิทธิ์แก้ไข
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
        $ids = [];
        foreach ($request->request('ids', [])->toInt() as $id) {
            $document = \Dms\Download\Model::getDocument($id);
            if ($document && Helper::canEditDocument($login, $document)) {
                $ids[] = (int) $document->id;
            }
        }
        if (empty($ids)) {
            return $this->errorResponse('Unable to complete the transaction', 400);
        }
        Model::remove($ids);
        \Index\Log\Model::add(0, 'dms', 'Delete', '{LNG_Delete} {LNG_Document} ID : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }
}
