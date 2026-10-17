<?php
/**
 * @filesource modules/dms/controllers/categories.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Dms\Categories;

class Controller extends \Index\Categories\Controller
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
