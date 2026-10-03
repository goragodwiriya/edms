<?php
/**
 * @filesource modules/dms/controllers/detail.php
 *
 * GET api/dms/detail/get?id=<dms_id> — รายละเอียดเอกสาร (modal)
 */

namespace Dms\Detail;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

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
            if (!Helper::canDownload($login)) {
                return $this->errorResponse('Permission required', 403);
            }
            $document = Model::get($request->get('id')->toInt());
            // ⚠️ ระบบเดิมเปิดรายละเอียดเอกสารของทุกแผนกได้ถ้ารู้ id — ที่นี่ใช้กติกาเดียวกับรายการ
            if (!$document || !\Dms\Download\Model::isVisible($document->id, $login)) {
                return $this->errorResponse('No data available', 404);
            }
            $document->files = $document->url === '' ? Model::files($document->id, $login) : [];
            $document->create_date = \Kotchasan\Date::format($document->create_date, 'd M Y');

            return $this->successResponse([
                'data' => $document,
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'show',
                        'template' => 'dms/detail.html',
                        'title' => '{LNG_Details of} {LNG_Document}',
                        'titleClass' => 'icon-file'
                    ]
                ]
            ], 'Document details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
