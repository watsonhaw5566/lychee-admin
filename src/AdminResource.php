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
     * 完整配置支持的键：
     *   - type:       字段类型（text / textarea / number / password / select / radio / checkbox / switch / image / file / richtext / date）
     *   - required:   是否必填（true / false），必填字段 label 会显示 *，并参与前后端校验
     *   - rules:      think-validate 规则字符串，如 'email'、'length:6,20'、'number|between:1,120'
     *   - options:    select / radio / checkbox 的可选项
     *   - multiple:   select 是否多选；image / file 是否允许多文件上传（多文件存储为 JSON 数组）
     *
     * 示例：
     *   'email'  => ['type' => 'text', 'required' => true, 'rules' => 'email']
     *   'images' => ['type' => 'image', 'multiple' => true]
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

    // ── 字段标签（由子类声明）──────────────────────────────────────

    /**
     * 字段中文标签，由子类声明。
     *
     * 未声明的字段会命中 {@see $fieldLabelMap} 内置映射，
     * 仍未命中则直接使用字段名本身。
     *
     * @var array<string, string>
     */
    protected array $fieldLabels = [];

    /**
     * 内置通用字段映射（create_time / update_time / id 等）。
     *
     * @var array<string, string>
     */
    protected array $fieldLabelMap = [
        'id'          => 'ID',
        'create_time' => '创建时间',
        'update_time' => '更新时间',
    ];

    /**
     * 获取所有字段标签。
     *
     * 优先级：子类 {@see $fieldLabels} > 内置 {@see $fieldLabelMap} > 字段名。
     *
     * @return array<string, string>
     */
    public function getFieldLabels(): array
    {
        return array_merge($this->fieldLabelMap, $this->fieldLabels);
    }

    /**
     * 获取单个字段的标签。
     */
    public function getFieldLabel(string $field): string
    {
        $labels = $this->getFieldLabels();

        return $labels[$field] ?? $field;
    }

    // ── 表单校验（由表单字段配置驱动）──────────────────────────────

    /**
     * 判断字段是否必填。
     *
     * 在表单字段配置中设置 `'required' => true` 即可。
     */
    public function isFieldRequired(string $field): bool
    {
        $fields = $this->getFormFields();
        $config = $fields[$field] ?? null;

        if (is_array($config)) {
            return !empty($config['required']);
        }

        return false;
    }

    /**
     * 获取字段的 think-validate 规则字符串（含 require）。
     *
     * 由 `required` 与 `rules` 配置组合而成，例如：
     *   required=true, rules='email'  → 'require|email'
     *   required=true, rules=''       → 'require'
     *   required=false, rules='email' → 'email'
     */
    public function getFieldRules(string $field): string
    {
        $fields = $this->getFormFields();
        $config = $fields[$field] ?? null;

        $rules = '';
        if (is_array($config) && !empty($config['rules'])) {
            $rules = (string) $config['rules'];
        }

        if ($this->isFieldRequired($field)) {
            $rules = $rules !== '' ? 'require|' . $rules : 'require';
        }

        return $rules;
    }

    /**
     * 获取字段对应的 layui lay-verify 值。
     *
     * 将 think-validate 规则映射为 layui 内置校验类型：
     *   require → required、email、url、number、date、identity
     *
     * switch / checkbox 类型不支持 layui 的 required 校验，返回空字符串（交由后端校验）。
     */
    public function getFieldLayVerify(string $field): string
    {
        $fields = $this->getFormFields();
        $config = $fields[$field] ?? null;
        $type   = is_array($config) ? ($config['type'] ?? 'text') : ($config ?? 'text');

        if (in_array($type, ['switch', 'checkbox'], true)) {
            return '';
        }

        $verify = [];
        if ($this->isFieldRequired($field)) {
            $verify[] = 'required';
        }

        $rules = $this->getFieldRules($field);
        if ($rules !== '') {
            $layuiMap = ['email', 'url', 'number', 'date', 'identity'];
            foreach (explode('|', $rules) as $seg) {
                $name = explode(':', $seg, 2)[0];
                if (in_array($name, $layuiMap, true)) {
                    $verify[] = $name;
                }
            }
        }

        return implode('|', array_unique($verify));
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
     * @param Model|null           $existing 编辑时的已有模型实例，新增时为 null
     * @return array<string, mixed>
     */
    protected function beforeSave(array $data, ?Model $existing = null): array
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
     * @param Model|null           $existing 编辑时的已有模型实例
     * @return array<string, mixed>
     */
    public function applyBeforeSave(array $data, ?Model $existing = null): array
    {
        return $this->beforeSave($data, $existing);
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
