<?php

declare(strict_types=1);

namespace LycheeAdmin\resource;

use LycheeAdmin\AdminResource;
use LycheeAdmin\model\Permission;
use LycheeAdmin\model\Role;

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

    protected array $fieldLabels = [
        'name'           => '角色名称',
        'description'    => '角色描述',
        'permission_ids' => '权限',
        'status'         => '状态',
    ];

    protected array $formFields = [
        'name'           => 'text',
        'description'    => 'textarea',
        'permission_ids' => ['type' => 'select', 'multiple' => true, 'options' => []],
    ];

    protected array $searchFields = ['name'];

    /**
     * 动态注入权限选项（从数据库读取）。
     */
    public function getFormFields(): array
    {
        $fields = $this->formFields;

        $permissions = Permission::column('name', 'id');
        $fields['permission_ids']['options'] = $permissions;

        return $fields;
    }
}
