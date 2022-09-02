<?php

declare(strict_types=1);
/**
 * This file is part of FirecmsExt Mail.
 *
 * @link     https://www.klmis.cn
 * @document https://www.klmis.cn
 * @contact  zhimengxingyun@klmis.cn
 * @license  https://github.com/firecms-ext/mail/blob/master/LICENSE
 */
namespace FirecmsExt\Mail\Events;

use Swift_Message;

class MailMessageSending
{
    /**
     * Swift 消息实例。
     */
    public Swift_Message $message;

    /**
     * 消息数据。
     */
    public array $data;

    /**
     * 创建一个新的事件实例。
     */
    public function __construct(Swift_Message $message, array $data = [])
    {
        $this->data = $data;
        $this->message = $message;
    }
}
