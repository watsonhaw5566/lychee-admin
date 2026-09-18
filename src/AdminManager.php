<?php

declare(strict_types=1);

namespace LycheeAdmin;

use RuntimeException;

/**
 * 后台资源注册表。
 *
 * 管理所有已注册的 AdminResource，提供查询与菜单生成功能。
 * 同模型的资源后注册会覆盖先注册的，允许用户覆盖内置资源。
 */
class AdminManager
{
    /**
     * 已注册的资源类。
     *
     * @var array<class-string, class-string<AdminResource>> modelClass => adminResourceClass
     */
    protected array $resources = [];

    /**
     * 注册一个资源。
     *
     * 以模型类名为 key，后注册的覆盖先注册的，
     * 因此用户可以注册自己的 AdminResource 来覆盖内置实现。
     *
     * @param class-string<AdminResource> $adminResourceClass
     */
    public function register(string $adminResourceClass): void
    {
        if (!is_subclass_of($adminResourceClass, AdminResource::class)) {
            throw new RuntimeException(
                "资源 [{$adminResourceClass}] 必须继承 " . AdminResource::class
            );
        }

        $resource = new $adminResourceClass();
        $model    = $resource->getModel();

        if ($model === '') {
            throw new RuntimeException(
                "资源 [{$adminResourceClass}] 未配置 model 属性"
            );
        }

        $this->resources[$model] = $adminResourceClass;
    }

    /**
     * 根据模型类名获取资源实例。
     *
     * @param string $model 模型类名
     */
    public function get(string $model): ?AdminResource
    {
        if (!isset($this->resources[$model])) {
            return null;
        }

        $class = $this->resources[$model];

        return new $class();
    }

    /**
     * 判断指定模型是否已注册资源。
     */
    public function has(string $model): bool
    {
        return isset($this->resources[$model]);
    }

    /**
     * 获取所有已注册的资源类。
     *
     * @return array<class-string, class-string<AdminResource>>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * 获取所有资源实例（用于渲染菜单）。
     *
     * @return array<int, AdminResource>
     */
    public function allInstances(): array
    {
        $instances = [];
        foreach ($this->resources as $class) {
            $instances[] = new $class();
        }

        return $instances;
    }

    /**
     * 生成菜单结构，按 group 分组。
     *
     * @return array<string, array<int, array{title:string, icon:string, model:string}>>
     */
    public function getMenu(): array
    {
        $menu = [];

        foreach ($this->allInstances() as $resource) {
            $group = $resource->getGroup();
            if (!isset($menu[$group])) {
                $menu[$group] = [];
            }

            $menu[$group][] = [
                'title' => $resource->getTitle(),
                'icon'  => $resource->getIcon(),
                'model' => $resource->getModel(),
            ];
        }

        return $menu;
    }
}
