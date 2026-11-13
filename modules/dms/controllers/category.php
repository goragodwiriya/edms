<?php
/**
 * @filesource modules/dms/controllers/category.php
 */

namespace Dms\Category;

class Controller extends \Gcms\Category
{
    /**
     * Supported category types and their labels.
     *
     * @var array
     */
    protected $categories = [
        'department' => '{LNG_Department}',
        'cabinet' => '{LNG_Cabinet}'
    ];
}
