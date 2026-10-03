<?php
/**
 * @filesource modules/dms/controllers/category.php
 */

namespace Dms\Category;

class Controller extends \Gcms\Category
{
    /**
     * หมวดหมู่ที่เอกสารใช้ (ค่าเก็บใน dms_meta ตาม type)
     * department = แผนก (ของแกน จัดการที่ ตั้งค่า > แผนก) · cabinet = ตู้เก็บเอกสาร
     *
     * @var array
     */
    protected $categories = [
        'department' => '{LNG_Department}',
        'cabinet' => '{LNG_Cabinet}'
    ];
}
