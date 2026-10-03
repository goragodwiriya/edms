<?php
/**
 * @filesource modules/dms/controllers/download.php
 *
 * ดาวน์โหลดไฟล์และเปิดลิงก์ของเอกสาร — ปุ่มในตารางเปิด URL เหล่านี้ในแท็บใหม่โดยตรง
 * (ยืนยันตัวตนด้วยคุกกี้ auth_token) จึงตอบเป็นไฟล์หรือ redirect ไม่ใช่ JSON
 *
 * GET api/dms/download?id=<file_id>         ผู้ดาวน์โหลด นับครั้ง (เดิม action download_)
 * GET api/dms/download/url?id=<dms_id>      ผู้ดาวน์โหลด เปิดลิงก์ของเอกสาร นับครั้ง
 * GET api/dms/download/manage?id=<file_id>  ผู้อัปโหลด (หน้าไฟล์ของเอกสาร) ไม่นับครั้ง
 */

namespace Dms\Download;

use Dms\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

class Controller extends ApiController
{
    /**
     * ดาวน์โหลดไฟล์ (ผู้ดาวน์โหลด)
     *
     * @param Request $request
     *
     * @return Response|void
     */
    public function index(Request $request)
    {
        $login = $this->authenticateRequest($request);
        if (!$login) {
            return $this->fail(401);
        }
        if (!Helper::canDownload($login)) {
            return $this->fail(403);
        }
        $file = Model::getFile($request->get('id')->toInt());
        if (!$file || $file->url !== '' || !Model::isVisible($file->dms_id, $login)) {
            return $this->fail(404);
        }
        $info = Model::prepare($file);
        if ($info === null) {
            return $this->fail(404);
        }
        Model::count($file->dms_id, $file->id, $login->id);

        return $this->stream($info);
    }

    /**
     * เปิดลิงก์ของเอกสารแบบ URL (ผู้ดาวน์โหลด)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function url(Request $request)
    {
        $login = $this->authenticateRequest($request);
        if (!$login) {
            return $this->fail(401);
        }
        if (!Helper::canDownload($login)) {
            return $this->fail(403);
        }
        $document = Model::getDocument($request->get('id')->toInt());
        if (!$document || !preg_match('/^https?:\/\//i', $document->url) || !Model::isVisible($document->id, $login)) {
            return $this->fail(404);
        }
        Model::count($document->id, 0, $login->id);

        return (new Response())->redirect($document->url);
    }

    /**
     * ดาวน์โหลดไฟล์จากหน้าไฟล์ของเอกสาร (ผู้อัปโหลด) — ไม่นับเป็นการดาวน์โหลด
     * เหมือนระบบเดิมที่ปุ่มนี้ลิงก์ตรงไปที่ไฟล์
     *
     * @param Request $request
     *
     * @return Response|void
     */
    public function manage(Request $request)
    {
        $login = $this->authenticateRequest($request);
        if (!$login) {
            return $this->fail(401);
        }
        $file = Model::getFile($request->get('id')->toInt());
        if (!$file) {
            return $this->fail(404);
        }
        if (!Helper::canEditDocument($login, $file)) {
            return $this->fail(403);
        }
        $info = Model::prepare($file);
        if ($info === null) {
            return $this->fail(404);
        }

        return $this->stream($info);
    }

    /**
     * ส่งไฟล์ออก
     *
     * @param array $info จาก Model::prepare()
     */
    protected function stream(array $info)
    {
        $name = str_replace(['"', "\r", "\n"], '', $info['name']);
        $disposition = ($info['inline'] ? 'inline' : 'attachment')
            .'; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($info['name']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: '.$info['mime']);
        header('Content-Disposition: '.$disposition);
        header('Content-Length: '.$info['size']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        readfile($info['path']);
        exit;
    }

    /**
     * ปุ่มเปิดในแท็บใหม่ จึงพากลับไปหน้าของระบบแทนการตอบ JSON
     *
     * @param int $code
     *
     * @return Response
     */
    protected function fail($code)
    {
        $pages = [401 => 'login', 403 => '403', 404 => '404'];

        return (new Response())->redirect(WEB_URL.($pages[$code] ?? '404'));
    }
}
