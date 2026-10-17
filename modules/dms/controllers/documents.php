<?php
/**
 * @filesource modules/dms/controllers/documents.php
 *
 * GET api/dms/documents — รายการเอกสารสำหรับผู้ดาวน์โหลด (เดิม module=dms)
 */

namespace Dms\Documents;

use Dms\Helper\Controller as Helper;
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
        if (!Helper::canDownload($login)) {
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
            'from' => $request->get('from')->date(),
            'to' => $request->get('to')->date(),
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
        return Model::toDataTable($params, $login);
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
            $item->file_id = (int) $item->file_id;
            // รหัสแถวต้องไม่ซ้ำ — เอกสารหนึ่งมีได้หลายไฟล์ และเอกสารแบบ URL ไม่มีไฟล์
            $item->id = $item->file_id > 0 ? 'f'.$item->file_id : 'd'.$item->dms_id;
            $item->is_url = $item->url === '' ? 0 : 1;
            $item->icon = $item->is_url === 0 && $item->ext !== null ? Helper::extIcon($item->ext) : '';
            $item->file_name = (string) $item->file_name;
            $item->department = (string) $item->department;
            $item->cabinet = (string) $item->cabinet;
            $item->downloaded = empty($item->downloads) ? 0 : 1;
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
        return [
            'cabinet' => Helper::category()->toOptions('cabinet')
        ];
    }
}
