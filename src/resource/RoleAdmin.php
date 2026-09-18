<?php

declare(strict_types=1);

namespace LycheeAdmin\resource;

use LycheeAdmin\AdminResource;use LycheeAdmin\model\Role;

/**
 * 角色资源。
 */
class RoleAdmin extends AdminResource
{
    protected string $model = Role::class;
    protected string $title = '角色';
    protected string $icon  = 'layui-icon layui-icon-group';
    protected string $group = '系统管理';

    protected array $listFields = ['id', 'name', 'description', 'create_time'];

    protected array $formFields = [
        'name'           => 'text',
        'description'    => 'textarea',
        'permission_ids' => ['type' => 'checkbox', 'options' => []],
    ];

    protected array $searchFields = ['name'];
}
