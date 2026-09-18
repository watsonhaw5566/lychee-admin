<?php

declare(strict_types=1);

use Lychee\migration\Seeder;

/**
 * lychee-admin 默认数据填充。
 *
 * 插入默认超级管理员账号：admin / admin123
 * 并为其生成若干示例消息通知。
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // 默认超级管理员：admin / admin123
        $this->insert('admin', [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'nickname' => '超级管理员',
            'email' => 'admin@example.com',
            'is_super' => 1,
            'status' => 1,
        ]);

        $adminId = 1;
        $now     = date('Y-m-d H:i:s');

        // 示例消息通知
        $this->insertBatch('admin_notification', [
            [
                'admin_id' => $adminId,
                'type'     => 'system',
                'title'    => '系统升级通知',
                'content'  => 'Lychee Admin v1.2.0 已发布，新增多标签页与消息中心功能。',
                'is_read'  => 0,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'admin_id' => $adminId,
                'type'     => 'notice',
                'title'    => '新管理员注册',
                'content'  => '用户 zhangsan 申请加入后台管理，请及时审核。',
                'is_read'  => 0,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'admin_id' => $adminId,
                'type'     => 'todo',
                'title'    => '待办：数据库备份',
                'content'  => '今日数据库自动备份已完成，请前往备份记录确认。',
                'is_read'  => 0,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'admin_id' => $adminId,
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
