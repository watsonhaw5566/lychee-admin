<?php

declare(strict_types=1);

namespace LycheeAdmin\resource;

use LycheeAdmin\AdminResource;
use LycheeAdmin\model\Menu;

/**
 * 菜单资源。
 */
class MenuAdmin extends AdminResource
{
    protected string $model = Menu::class;
    protected string $title = '菜单';
    protected string $icon  = 'layui-icon layui-icon-menu-fill';
    protected string $group = '系统管理';

    protected array $listFields = ['id', 'parent_id', 'title', 'icon', 'path', 'sort', 'status'];

    protected array $fieldLabels = [
        'parent_id' => '父级菜单',
        'title'     => '菜单标题',
        'icon'      => '图标',
        'path'      => '路由路径',
        'sort'      => '排序',
        'status'    => '状态',
    ];

    protected array $formFields = [
        'parent_id' => ['type' => 'select', 'options' => [0 => '顶级菜单']],
        'title'     => 'text',
        'icon'      => 'text',
        'path'      => 'text',
        'sort'      => ['type' => 'number'],
        'status'    => ['type' => 'select', 'options' => [1 => '显示', 0 => '隐藏']],
    ];

    protected array $searchFields = ['title', 'path'];

    protected array $filterFields = ['status'];

    /**
     * 动态注入父级菜单选项。
     */
    public function getFormFields(): array
    {
        $fields = $this->formFields;

        $menus = Menu::column('title', 'id');
        $fields['parent_id']['options'] = [0 => '顶级菜单'] + $menus;

        return $fields;
    }
}
