<?php

declare(strict_types=1);

namespace LycheeAdmin\model;

use think\Model;

/**
 * 管理员模型。
 */
class Admin extends Model
{
    protected $name = 'admin';

    protected $pk = 'id';

    /** 自动写入时间戳 */
    protected $autoWriteTimestamp = true;

    /** JSON 字段 */
    protected $json = ['role_ids'];

    /**
     * 密码加密：保存时自动 hash。
     */
    public function setPasswordAttr($value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        // 已经是 hash（长度 60）则不重复加密
        if (is_string($value) && strlen($value) === 60) {
            return $value;
        }

        return password_hash((string) $value, PASSWORD_DEFAULT);
    }
}
