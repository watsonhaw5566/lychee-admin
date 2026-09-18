<?php

declare(strict_types=1);

namespace LycheeAdmin\model;

use think\Model;

/**
 * 角色模型。
 */
class Role extends Model
{
    protected $name = 'admin_role';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    /** JSON 字段：权限 ID 列表 */
    protected $json = ['permission_ids'];
}
