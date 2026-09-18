<?php

declare(strict_types=1);

use Lychee\migration\Seeder;

/**
 * lychee-admin 默认数据填充。
 *
 * 插入默认超级管理员账号：admin / admin123
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
    }
}
