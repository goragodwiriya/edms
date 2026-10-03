<?php
/**
 * @filesource modules/dms/controllers/report.php
 *
 * GET api/dms/report?file_id=   ประวัติการดาวน์โหลดของไฟล์ (เดิม module=dms-report)
 */

namespace Dms\Report;

use Dms\Helper\Controller as Helper;
use Kotchasan\Http\Request;

class Controller extends \Gcms\Table
{
    /**
     * @var array
     */
    protected $allowedSortColumns = ['name', 'updated_at', 'downloads'];

    /**
     * ไฟล์ที่กำลังดู (อ่านใน checkAuthorization)
     *
     * @var object|null
     */
    protected $file = null;

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
        $this->file = \Dms\Download\Model::getFile($request->get('file_id')->toInt());
        if (!$this->file) {
            return $this->errorResponse('No data available', 404);
        }
        if (!Helper::canEditDocument($login, $this->file)) {
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
            'dms_id' => $this->file ? (int) $this->file->dms_id : 0,
            'file_id' => $this->file ? (int) $this->file->id : 0
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
     * สถานะสมาชิก (lookup) และข้อมูลไฟล์สำหรับหัวหน้า
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getOptions(array $params, $login)
    {
        return [
            'status' => \Gcms\Controller::getUserStatusOptions(),
            'file' => $this->file ? [
                'id' => (int) $this->file->id,
                'dms_id' => (int) $this->file->dms_id,
                'name' => $this->file->name.'.'.$this->file->ext
            ] : null
        ];
    }
}
