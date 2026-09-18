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
    protected string $icon  = 'layui-icon layui-icon-menu';
    protected string $group = '系统管理';

    protected array $listFields = ['id', 'parent_id', 'title', 'icon', 'path', 'sort', 'status'];

    protected array $formFields = [
        'parent_id' => ['type' => 'number'],
        'title'     => 'text',
        'icon'      => 'text',
        'path'      => 'text',
        'sort'      => ['type' => 'number'],
        'status'    => ['type' => 'radio', 'options' => [1 => '显示', 0 => '隐藏']],
    ];

    protected array $searchFields = ['title', 'path'];

    protected array $filterFields = ['status'];
}
