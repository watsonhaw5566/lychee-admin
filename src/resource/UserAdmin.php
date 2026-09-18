<?php

declare(strict_types=1);

namespace LycheeAdmin\resource;

use LycheeAdmin\AdminResource;
use LycheeAdmin\model\Admin;
use LycheeAdmin\model\Role;
use think\Model;

/**
 * 管理员用户资源。
 */
class UserAdmin extends AdminResource
{
    protected string $model = Admin::class;
    protected string $title = '管理员';
    protected string $icon  = 'layui-icon layui-icon-username';
    protected string $group = '系统管理';

    protected array $listFields = ['id', 'username', 'nickname', 'email', 'status', 'create_time'];

    protected array $fieldLabels = [
        'username'   => '用户名',
        'password'   => '密码',
        'nickname'   => '昵称',
        'email'      => '邮箱',
        'avatar'     => '头像',
        'role_ids'   => '角色',
        'is_super'   => '超级管理员',
        'status'     => '状态',
        'last_login' => '最后登录时间',
    ];

    protected array $formFields = [
        'username' => 'text',
        'nickname' => 'text',
        'password' => 'password',
        'email'    => 'text',
        'role_ids' => ['type' => 'select', 'multiple' => true, 'options' => []],
        'status'   => ['type' => 'select', 'options' => [1 => '启用', 0 => '禁用']],
    ];

    protected array $searchFields = ['username', 'nickname'];

    protected array $filterFields = ['status'];

    /**
     * 动态注入角色选项（从数据库读取）。
     */
    public function getFormFields(): array
    {
        $fields = $this->formFields;

        $roles = Role::column('name', 'id');
        $fields['role_ids']['options'] = $roles;

        return $fields;
    }

    /**
     * 编辑时密码为空则不更新。
     */
    protected function beforeSave(array $data): array
    {
        if (isset($data['password']) && $data['password'] === '') {
            unset($data['password']);
        }

        return $data;
    }

    protected function formatList(Model $item): array
    {
        $data = $item->toArray();
        $data['status_text'] = (int) $item->status === 1 ? '启用' : '禁用';

        return $data;
    }
}
