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

## License

MIT
