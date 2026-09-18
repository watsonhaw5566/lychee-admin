<?php

declare(strict_types=1);

namespace LycheeAdmin;

use think\db\Query;
use think\Model;

/**
 * 后台资源配置基类（类似 Django 的 ModelAdmin）。
 *
 * 每个需要在后台管理的模型对应一个 AdminResource 子类，
 * 通过声明属性来配置列表字段、表单字段、搜索、筛选等，
 * 框架根据配置自动生成 CRUD 界面。
 *
 * 子类只需声明属性并覆盖钩子方法即可，无需编写控制器。
 */
abstract class AdminResource
{
    /** 模型类名 */
    protected string $model = '';

    /** 菜单标题 */
    protected string $title = '';

    /** 菜单图标（layui/pear 图标类名） */
    protected string $icon = '';

    /** 菜单分组 */
    protected string $group = '默认';

    /** 列表显示字段 */
    protected array $listFields = [];

    /**
     * 表单字段配置。
     *
     * 支持两种写法：
     *   'name' => 'text'                                    // 简写：字段名 => 类型
     *   'status' => ['type' => 'select', 'options' => [...]] // 完整配置
     *
     * 支持的类型：text、textarea、number、password、select、radio、checkbox、switch、image、file、richtext、date
     */
    protected array $formFields = [];

    /** 搜索字段（列表页顶部搜索框） */
    protected array $searchFields = [];

    /** 筛选字段（列表页筛选条件，如状态、类型） */
    protected array $filterFields = [];

    /** 是否允许新增 */
    protected bool $canCreate = true;

    /** 是否允许编辑 */
    protected bool $canUpdate = true;

    /** 是否允许删除 */
    protected bool $canDelete = true;

    /** 列表默认排序 */
    protected array $defaultOrder = ['id' => 'desc'];

    // ── 访问器（供 AdminController 读取配置）──────────────────────

    public function getModel(): string
    {
        return $this->model;
    }

    public function getTitle(): string
    {
        if ($this->title !== '') {
            return $this->title;
        }

        $class = static::class;
        $short = substr($class, strrpos($class, '\\') + 1);

        return preg_replace('/Admin$/', '', $short) ?? $short;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @return array<int, string>
     */
    public function getListFields(): array
    {
        return $this->listFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormFields(): array
    {
        return $this->formFields;
    }

    /**
     * @return array<int, string>
     */
    public function getSearchFields(): array
    {
        return $this->searchFields;
    }

    /**
     * @return array<int, string>
     */
    public function getFilterFields(): array
    {
        return $this->filterFields;
    }

    public function canCreate(): bool
    {
        return $this->canCreate;
    }

    public function canUpdate(): bool
    {
        return $this->canUpdate;
    }

    public function canDelete(): bool
    {
        return $this->canDelete;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultOrder(): array
    {
        return $this->defaultOrder;
    }

    // ── 钩子方法（子类可覆盖）────────────────────────────────────

    /**
     * 列表查询前，可追加自定义查询条件或关联预加载。
     */
    protected function indexQuery(Query $query): Query
    {
        return $query;
    }

    /**
     * 保存前处理数据（新增和编辑都会调用）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function beforeSave(array $data): array
    {
        return $data;
    }

    /**
     * 保存后处理（新增和编辑都会调用）。
     */
    protected function afterSave(Model $model): void
    {
    }

    /**
     * 删除前处理。
     */
    protected function beforeDelete(Model $model): void
    {
    }

    /**
     * 格式化列表单条数据（如枚举转文本、时间戳格式化）。
     *
     * @return array<string, mixed>
     */
    protected function formatList(Model $item): array
    {
        return $item->toArray();
    }

    // ── 内部方法（供 AdminController 调用）────────────────────────

    /**
     * 获取模型实例。
     */
    public function newModel(): Model
    {
        $class = $this->model;

        return new $class();
    }

    /**
     * 执行 indexQuery 钩子。
     */
    public function applyIndexQuery(Query $query): Query
    {
        return $this->indexQuery($query);
    }

    /**
     * 执行 beforeSave 钩子。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function applyBeforeSave(array $data): array
    {
        return $this->beforeSave($data);
    }

    /**
     * 执行 afterSave 钩子。
     */
    public function applyAfterSave(Model $model): void
    {
        $this->afterSave($model);
    }

    /**
     * 执行 beforeDelete 钩子。
     */
    public function applyBeforeDelete(Model $model): void
    {
        $this->beforeDelete($model);
    }

    /**
     * 执行 formatList 钩子。
     *
     * @return array<string, mixed>
     */
    public function applyFormatList(Model $item): array
    {
        return $this->formatList($item);
    }
}
