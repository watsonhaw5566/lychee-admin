<?php

declare(strict_types=1);

namespace LycheeAdmin\resource;

use LycheeAdmin\AdminResource;
use LycheeAdmin\model\Permission;

/**
 * 权限资源。
 */
class PermissionAdmin extends AdminResource
{
    protected string $model = Permission::class;
    protected string $title = '权限';
    protected string $icon  = 'layui-icon layui-icon-auz';
    protected string $group = '系统管理';

    protected array $listFields = ['id', 'name', 'code', 'description', 'create_time'];

    protected array $fieldLabels = [
        'name'        => '权限名称',
        'code'        => '权限标识',
        'description' => '权限描述',
    ];

    protected array $formFields = [
        'name'        => 'text',
        'code'        => 'text',
        'description' => 'textarea',
    ];

    protected array $searchFields = ['name', 'code'];
}
