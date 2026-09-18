<?php

declare(strict_types=1);

namespace LycheeAdmin\model;

use think\Model;

/**
 * 菜单模型。
 */
class Menu extends Model
{
    protected $name = 'admin_menu';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
}
