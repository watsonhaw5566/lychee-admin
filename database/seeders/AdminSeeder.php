<?php

declare(strict_types=1);

use Lychee\migration\Seeder;

/**
 * lychee-admin 默认数据填充。
 *
 * 写入权限、菜单、角色、默认超级管理员（admin / admin123）
 * 以及若干示例消息通知。
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ── 权限 ──────────────────────────────────────────────────
        $this->insertBatch('admin_permission', [
            ['name' => '管理员列表',   'code' => 'system:admin:list',      'description' => '查看管理员列表',   'create_time' => $now, 'update_time' => $now],
            ['name' => '新增管理员',   'code' => 'system:admin:create',    'description' => '新增管理员',       'create_time' => $now, 'update_time' => $now],
            ['name' => '编辑管理员',   'code' => 'system:admin:update',    'description' => '编辑管理员',       'create_time' => $now, 'update_time' => $now],
            ['name' => '删除管理员',   'code' => 'system:admin:delete',    'description' => '删除管理员',       'create_time' => $now, 'update_time' => $now],
            ['name' => '角色列表',     'code' => 'system:role:list',       'description' => '查看角色列表',     'create_time' => $now, 'update_time' => $now],
            ['name' => '新增角色',     'code' => 'system:role:create',     'description' => '新增角色',         'create_time' => $now, 'update_time' => $now],
            ['name' => '编辑角色',     'code' => 'system:role:update',     'description' => '编辑角色',         'create_time' => $now, 'update_time' => $now],
            ['name' => '删除角色',     'code' => 'system:role:delete',     'description' => '删除角色',         'create_time' => $now, 'update_time' => $now],
            ['name' => '菜单列表',     'code' => 'system:menu:list',       'description' => '查看菜单列表',     'create_time' => $now, 'update_time' => $now],
            ['name' => '新增菜单',     'code' => 'system:menu:create',     'description' => '新增菜单',         'create_time' => $now, 'update_time' => $now],
            ['name' => '编辑菜单',     'code' => 'system:menu:update',     'description' => '编辑菜单',         'create_time' => $now, 'update_time' => $now],
            ['name' => '删除菜单',     'code' => 'system:menu:delete',     'description' => '删除菜单',         'create_time' => $now, 'update_time' => $now],
            ['name' => '权限列表',     'code' => 'system:permission:list', 'description' => '查看权限列表',     'create_time' => $now, 'update_time' => $now],
            ['name' => '新增权限',     'code' => 'system:permission:create','description' => '新增权限',        'create_time' => $now, 'update_time' => $now],
            ['name' => '编辑权限',     'code' => 'system:permission:update','description' => '编辑权限',        'create_time' => $now, 'update_time' => $now],
            ['name' => '删除权限',     'code' => 'system:permission:delete','description' => '删除权限',        'create_time' => $now, 'update_time' => $now],
        ]);

        // ── 菜单 ──────────────────────────────────────────────────
        $this->insertBatch('admin_menu', [
            ['parent_id' => 0, 'title' => '系统管理', 'icon' => 'layui-icon layui-icon-set',        'path' => '',            'sort' => 0, 'status' => 1, 'create_time' => $now, 'update_time' => $now],
            ['parent_id' => 1, 'title' => '管理员',   'icon' => 'layui-icon layui-icon-username',   'path' => '/admin/admin','sort' => 1, 'status' => 1, 'create_time' => $now, 'update_time' => $now],
            ['parent_id' => 1, 'title' => '角色',     'icon' => 'layui-icon layui-icon-group',      'path' => '/admin/role', 'sort' => 2, 'status' => 1, 'create_time' => $now, 'update_time' => $now],
            ['parent_id' => 1, 'title' => '菜单',     'icon' => 'layui-icon layui-icon-menu-fill',  'path' => '/admin/menu', 'sort' => 3, 'status' => 1, 'create_time' => $now, 'update_time' => $now],
            ['parent_id' => 1, 'title' => '权限',     'icon' => 'layui-icon layui-icon-auz',        'path' => '/admin/permission', 'sort' => 4, 'status' => 1, 'create_time' => $now, 'update_time' => $now],
        ]);

        // ── 角色 ──────────────────────────────────────────────────
        // permission_ids 为 JSON 字段，需手动 json_encode
        $allPermissions = range(1, 16);
        $editPermissions = [1, 2, 3, 5, 6, 7, 9, 10, 11, 13, 14, 15]; // 无 delete
        $viewPermissions = [1, 5, 9, 13]; // 仅 list

        $this->insertBatch('admin_role', [
            [
                'name'           => '超级管理员',
                'description'    => '拥有系统全部权限',
                'permission_ids' => json_encode($allPermissions),
                'status'         => 1,
                'create_time'    => $now,
                'update_time'    => $now,
            ],
            [
                'name'           => '管理员',
                'description'    => '可新增、编辑，但不可删除',
                'permission_ids' => json_encode($editPermissions),
                'status'         => 1,
                'create_time'    => $now,
                'update_time'    => $now,
            ],
            [
                'name'           => '访客',
                'description'    => '仅可查看列表',
                'permission_ids' => json_encode($viewPermissions),
                'status'         => 1,
                'create_time'    => $now,
                'update_time'    => $now,
            ],
        ]);

        // ── 默认超级管理员：admin / admin123 ──────────────────────
        $this->insert('admin', [
            'username'    => 'admin',
            'password'    => password_hash('admin123', PASSWORD_DEFAULT),
            'nickname'    => '超级管理员',
            'email'       => 'admin@example.com',
            'is_super'    => 1,
            'status'      => 1,
            'role_ids'    => json_encode([1]), // 超级管理员角色
            'create_time' => $now,
            'update_time' => $now,
        ]);

        // ── 示例消息通知 ──────────────────────────────────────────
        $this->insertBatch('admin_notification', [
            [
                'admin_id' => 1,
                'type'     => 'system',
                'title'    => '系统升级通知',
                'content'  => 'Lychee Admin v1.2.0 已发布，新增多标签页与消息中心功能。',
                'is_read'  => 0,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'admin_id' => 1,
                'type'     => 'notice',
                'title'    => '新管理员注册',
                'content'  => '用户 zhangsan 申请加入后台管理，请及时审核。',
                'is_read'  => 0,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'admin_id' => 1,
                'type'     => 'todo',
                'title'    => '待办：数据库备份',
                'content'  => '今日数据库自动备份已完成，请前往备份记录确认。',
                'is_read'  => 0,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'admin_id' => 1,
                'type'     => 'notice',
                'title'    => '权限变更提醒',
                'content'  => '角色「编辑」的权限配置已被超级管理员修改。',
                'is_read'  => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
        ]);
    }
}
