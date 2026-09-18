# lychee-admin

lychee-php 的后台管理插件，安装后即可获得开箱即用的基础 admin 后台功能（前后端不分离）。

基于动态资源管理设计，类似 Django Admin：注册模型即可自动获得 CRUD 界面。

## 安装

```bash
composer require watsonhaw/lychee-admin
```

## 启用

在项目 `config/plugin.php` 中注册插件入口类：

```php
<?php

return [
    'providers' => [
        LycheeAdmin\AdminServiceProvider::class,
    ],
];
```

插件会在框架启动时自动完成：

- 绑定 `AdminManager` 到容器
- 注册后台路由（`/admin`）
- 注册 `@admin` Twig 视图命名空间
- 注册内置资源（用户、角色、菜单、权限）
- 注册 `super_admin` 鉴权中间件
- 注册插件的迁移与 Seeder 路径
- 注册 `admin:publish` 控制台命令

## 数据库

插件自带迁移与数据填充，执行以下命令建表并写入默认管理员：

```bash
# 执行迁移，创建 admin / admin_role / admin_menu / admin_permission 表
php lee migrate:run

# 执行数据填充，插入默认超级管理员（admin / admin123）
php lee seed:run
```

## 目录结构

```
lychee-admin/
├── asset/                  # 静态资源（发布到 public/lychee/）
│   ├── admin/              # 后台 CSS、图片
│   └── component/          # layui、pear 组件
├── database/
│   ├── migrations/         # 数据库迁移
│   └── seeders/            # 数据填充
├── src/
│   ├── AdminResource.php   # 资源配置基类
│   ├── AdminManager.php    # 资源注册表
│   ├── AdminController.php # 通用 CRUD 控制器
│   ├── AdminServiceProvider.php
│   ├── middleware/         # 鉴权中间件
│   ├── model/              # 数据模型
│   └── resource/           # 内置资源
└── view/                   # Twig 模板
```

## 静态资源发布

`asset/` 目录下的静态资源需要发布到项目的 `public/lychee/` 目录。

> 注意：静态资源目录使用 `lychee` 而非 `admin`，因为 `/admin` 已被用作后台路由前缀，若静态资源也放在 `public/admin/` 会导致路由失效。

提供了 `admin:publish` 控制台命令方便发布：

```bash
# 复制静态资源到 public/lychee/
php lee admin:publish

# 强制覆盖已存在的文件
php lee admin:publish --force

# 创建符号链接（开发环境推荐，修改资源即时生效）
php lee admin:publish --link
```

也可以手动操作：

```bash
# 方式一：符号链接（开发环境推荐）
ln -s vendor/watsonhaw/lychee-admin/asset public/lychee

# 方式二：复制（生产环境）
cp -r vendor/watsonhaw/lychee-admin/asset/* public/lychee/
```

生产环境建议通过 Nginx 直接映射静态资源目录。

## 使用

访问 `/admin`，使用默认账号 `admin` / `admin123` 登录。

## 注册资源

每个需要在后台管理的模型对应一个继承 `AdminResource` 的子类，声明配置属性即可自动获得 CRUD 界面，无需编写控制器。

```php
<?php

namespace App\Admin;

use LycheeAdmin\AdminResource;
use App\Model\Article;

class ArticleAdmin extends AdminResource
{
    protected string $model = Article::class;
    protected string $title = '文章';
    protected string $icon  = 'layui-icon layui-icon-read';
    protected string $group = '内容管理';

    /** 列表显示字段 */
    protected array $listFields = ['id', 'title', 'category_id', 'status', 'create_time'];

    /** 表单字段：字段名 => 类型（或完整配置数组） */
    protected array $formFields = [
        'title'       => ['type' => 'text', 'required' => true],
        'category_id' => ['type' => 'select', 'options' => [1 => '技术', 2 => '生活']],
        'content'     => 'textarea',
        'status'      => ['type' => 'radio', 'options' => [1 => '发布', 0 => '草稿']],
    ];

    protected array $searchFields = ['title'];
    protected array $filterFields = ['status'];
}
```

### 注册资源到后台

资源类创建后，只需在项目 `config/admin.php` 中声明 `resources` 数组，框架启动时会自动注册，**无需编写插件入口类**。

```php
<?php
// config/admin.php

return [
    'resources' => [
        App\Admin\ArticleAdmin::class,
        App\Admin\CategoryAdmin::class,
    ],
];
```

> 注册以**模型类名为 key**，用户资源在内置资源之后注册，因此会自动覆盖内置的同名模型资源。

**完整流程**：

```
1. 创建资源类  → src/Admin/ArticleAdmin.php（继承 AdminResource）
2. 注册资源    → 在 config/admin.php 的 resources 数组中加入类名
3. 刷新页面    → 后台菜单自动出现你的资源，CRUD 界面自动生成
```

### 字段标签

列表表头、表单 label、搜索框占位符默认使用英文字段名。通过 `$fieldLabels` 声明中文字段名：

```php
protected array $fieldLabels = [
    'title'       => '标题',
    'category_id' => '分类',
    'content'     => '内容',
    'status'      => '状态',
];
```

**解析优先级**：

1. 子类声明的 `$fieldLabels`
2. 内置通用映射（`id` → ID、`create_time` → 创建时间、`update_time` → 更新时间）
3. 字段名本身（兜底）

> 字段标签完全由代码配置驱动，不读取数据库 comment，因此对所有数据库类型通用。未声明的字段会直接显示英文字段名。

### 支持的表单字段类型

`text`、`textarea`、`number`、`password`、`select`、`radio`、`checkbox`、`switch`、`image`、`file`、`richtext`、`date`。

其中 `richtext` 类型集成了 [wangEditor-next](https://wangeditor-next.github.io/docs/guide/getting-started) 富文本编辑器，支持图片上传（通过 `/admin/upload` 接口）、内容编辑、HTML 回填等功能。编辑器内容在提交时自动同步到表单字段。

### 表单校验

表单字段支持 `required` 和 `rules` 两个配置项，同时驱动前端 layui 校验与后端 think-validate 校验。

```php
protected array $formFields = [
    'username' => ['type' => 'text', 'required' => true, 'rules' => 'length:2,20'],
    'email'    => ['type' => 'text', 'required' => true, 'rules' => 'email'],
    'password' => ['type' => 'password', 'required' => true, 'rules' => 'length:6,20'],
    'status'   => ['type' => 'select', 'options' => [1 => '启用', 0 => '禁用'], 'required' => true],
];
```

- **`required`**：是否必填。必填字段的 label 会显示红色 `*`，前端通过 `lay-verify="required"` 拦截空提交，后端通过 `require` 规则校验。
- **`rules`**：think-validate 规则字符串，多个规则用 `|` 分隔，如 `email`、`length:6,20`、`number|between:1,120`。框架会自动将 `email`、`url`、`number`、`date` 等常用规则映射到 layui 的 `lay-verify`，其余规则由后端兜底。

**编辑时的特殊处理**：编辑时若某字段未提交（如留空的密码字段、未重新上传的文件），框架会跳过该字段的 `required` 校验，保留原有值。

常用规则速查：`require`、`email`、`url`、`number`、`integer`、`date`、`length:min,max`、`max:n`、`min:n`、`between:a,b`、`in:a,b,c`、`regex:/pattern/`。更多规则参见 [think-validate 文档](https://doc.thinkphp.cn/@think-validate)。

### 字段类型详表

| 类型 | 说明 | 常用配置项 |
|------|------|-----------|
| `text` | 单行文本 | `required`, `rules` |
| `textarea` | 多行文本 | `required`, `rules` |
| `number` | 数字输入 | `required`, `rules` |
| `password` | 密码（编辑时留空不修改原值） | `required`, `rules` |
| `select` | 下拉选择 | `options`, `multiple`, `required` |
| `radio` | 单选按钮 | `options`, `required` |
| `checkbox` | 多选框 | `options` |
| `switch` | 开关 | — |
| `date` | 日期选择 | `required` |
| `image` | 图片上传（单张） | `multiple` 开启多图 |
| `file` | 文件上传（单个） | `multiple` 开启多文件 |
| `richtext` | 富文本编辑器（wangEditor-next） | — |

**多图 / 多文件上传**：将 `image` 或 `file` 类型的 `multiple` 设为 `true`，字段将以 `name="field[]"` 渲染并支持多选，存储为 JSON 数组字符串（如 `["uploads/admin/2026-09/a.png","uploads/admin/2026-09/b.png"]`）。编辑时自动解码回显。

```php
protected array $formFields = [
    'gallery'     => ['type' => 'image', 'multiple' => true],
    'attachments' => ['type' => 'file', 'multiple' => true],
];
```

### 权限控制

通过以下布尔属性控制资源的增删改权限（默认全部为 `true`）：

```php
protected bool $canCreate = true;   // 是否允许新增
protected bool $canUpdate = true;   // 是否允许编辑
protected bool $canDelete = true;   // 是否允许删除
```

设为 `false` 后，对应按钮不会在列表页显示，且后端接口也会拒绝操作。

### 钩子方法

子类可覆盖以下钩子方法，在不修改控制器的前提下注入自定义业务逻辑：

| 钩子 | 调用时机 | 用途 |
|------|---------|------|
| `indexQuery(Query $query)` | 列表查询前 | 追加查询条件、关联预加载 |
| `beforeSave(array $data, ?Model $existing)` | 保存前（新增+编辑） | 处理/补全数据 |
| `afterSave(Model $model)` | 保存后（新增+编辑） | 清缓存、发通知等 |
| `beforeDelete(Model $model)` | 删除前 | 检查关联数据 |
| `formatList(Model $item)` | 列表数据格式化 | 枚举转文本、时间戳格式化 |

```php
use think\db\Query;
use think\Model;

class ArticleAdmin extends AdminResource
{
    // 列表查询：只查未删除的，预加载分类
    protected function indexQuery(Query $query): Query
    {
        return $query->with('category')->where('deleted', 0);
    }

    // 保存前：新增时写入作者 ID
    protected function beforeSave(array $data, ?Model $existing = null): array
    {
        if ($existing === null) {
            $data['author_id'] = session('admin_id');
        }
        return $data;
    }

    // 列表格式化：状态码转文本
    protected function formatList(Model $item): array
    {
        $arr = $item->toArray();
        $arr['status_text'] = $item->status ? '发布' : '草稿';
        return $arr;
    }
}
```

### 覆盖内置资源

插件内置了 `UserAdmin`、`RoleAdmin`、`MenuAdmin`、`PermissionAdmin` 四个资源。如需自定义（如给用户表单增加字段、修改查询逻辑），继承内置资源类并重新注册即可：

```php
<?php

namespace App\Admin;

use LycheeAdmin\Admin\UserAdmin;

class MyUserAdmin extends UserAdmin
{
    // 覆盖表单字段，增加手机号字段
    protected array $formFields = [
        'username' => ['type' => 'text', 'required' => true],
        'phone'    => ['type' => 'text', 'rules' => 'mobile'],
        'password' => ['type' => 'password', 'required' => true],
        'roles'    => ['type' => 'checkbox', 'options' => []],
        'status'   => ['type' => 'switch'],
    ];

    // 覆盖钩子
    protected function beforeSave(array $data, ?Model $existing = null): array
    {
        return parent::beforeSave($data, $existing);
    }
}
```

然后在 `config/admin.php` 中注册你的自定义资源即可覆盖内置实现：

```php
// config/admin.php
return [
    'resources' => [
        App\Admin\MyUserAdmin::class,
    ],
];
```

> 注册以**模型类名为 key**，用户资源在内置资源之后注册，因此会自动覆盖内置的同名模型资源。

## License

MIT
