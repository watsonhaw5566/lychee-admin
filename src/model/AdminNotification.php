<?php

declare(strict_types=1);

namespace LycheeAdmin\model;

use think\Model;

/**
 * 后台消息通知模型。
 */
class AdminNotification extends Model
{
    protected $name = 'admin_notification';

    protected $pk = 'id';

    /** 自动写入时间戳 */
    protected $autoWriteTimestamp = true;
}
