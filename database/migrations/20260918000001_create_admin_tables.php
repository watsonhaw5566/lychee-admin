<?php

declare(strict_types=1);

use Lychee\migration\Migration;

/**
 * 创建 lychee-admin 后台数据表。
 *
 * 包含：管理员、角色、菜单、权限四张表。
 */
class CreateAdminTablesMigration extends Migration
{
    public function up(): void
    {
        // 管理员表
        $this->table('admin')
            ->addColumn('username', 'string', ['length' => 50, 'comment' => '用户名'])
            ->addColumn('password', 'string', ['length' => 255, 'comment' => '密码（bcrypt）'])
            ->addColumn('nickname', 'string', ['length' => 50, 'default' => '', 'comment' => '昵称'])
            ->addColumn('email', 'string', ['length' => 100, 'default' => '', 'comment' => '邮箱'])
            ->addColumn('avatar', 'string', ['length' => 255, 'default' => '', 'comment' => '头像'])
            ->addColumn('role_ids', 'json', ['null' => true, 'comment' => '角色ID列表'])
            ->addColumn('is_super', 'boolean', ['default' => 0, 'comment' => '是否超级管理员'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addColumn('last_login', 'datetime', ['null' => true, 'comment' => '最后登录时间'])
            ->addTimestamps()
            ->addIndex('username', ['type' => 'UNIQUE', 'name' => 'uk_username'])
            ->setComment('后台管理员表')
            ->create();

        // 角色表
        $this->table('admin_role')
            ->addColumn('name', 'string', ['length' => 50, 'comment' => '角色名称'])
            ->addColumn('description', 'string', ['length' => 255, 'default' => '', 'comment' => '角色描述'])
            ->addColumn('permission_ids', 'json', ['null' => true, 'comment' => '权限ID列表'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addTimestamps()
            ->addIndex('name', ['type' => 'UNIQUE', 'name' => 'uk_name'])
            ->setComment('后台角色表')
            ->create();

        // 菜单表
        $this->table('admin_menu')
            ->addColumn('parent_id', 'integer', ['default' => 0, 'comment' => '父级菜单ID'])
            ->addColumn('title', 'string', ['length' => 50, 'comment' => '菜单标题'])
            ->addColumn('icon', 'string', ['length' => 100, 'default' => '', 'comment' => '图标类名'])
            ->addColumn('path', 'string', ['length' => 255, 'default' => '', 'comment' => '路由路径'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序（升序）'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态：0隐藏 1显示'])
            ->addTimestamps()
            ->addIndex('parent_id', ['name' => 'idx_parent'])
            ->setComment('后台菜单表')
            ->create();

        // 权限表
        $this->table('admin_permission')
            ->addColumn('name', 'string', ['length' => 50, 'comment' => '权限名称'])
            ->addColumn('code', 'string', ['length' => 100, 'comment' => '权限标识'])
            ->addColumn('description', 'string', ['length' => 255, 'default' => '', 'comment' => '权限描述'])
            ->addTimestamps()
            ->addIndex('code', ['type' => 'UNIQUE', 'name' => 'uk_code'])
            ->setComment('后台权限表')
            ->create();
    }

    public function down(): void
    {
        $this->table('admin')->drop();
        $this->table('admin_role')->drop();
        $this->table('admin_menu')->drop();
        $this->table('admin_permission')->drop();
    }
}
