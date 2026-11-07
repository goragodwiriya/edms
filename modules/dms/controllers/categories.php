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
     * Category types managed on this page. Departments are shared by every module,
     * so they stay on the core page /categories?type=department.
     *
     * @var array
     */
    protected $categories = [
        'cabinet' => '{LNG_Cabinet}'
    ];

    /**
     * A cabinet manager (can_manage_dms) works on cabinets without the
     * site-wide can_config permission.
     *
     * @var array
     */
    protected $permission = ['can_config', 'can_manage_dms'];
}
