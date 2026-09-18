<?php

declare(strict_types=1);

namespace LycheeAdmin\model;

use think\Model;

/**
 * 权限模型。
 */
class Permission extends Model
{
    protected $name = 'admin_permission';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
}
