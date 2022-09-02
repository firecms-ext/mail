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

use Swift_Attachment;
use Swift_Message;

class MailMessageSent
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

    /**
     * 获取对象的可序列化表示形式。
     *
     * @return array
     */
    public function __serialize()
    {
        $hasAttachments = collect($this->message->getChildren())
            ->whereInstanceOf(Swift_Attachment::class)
            ->isNotEmpty();

        return $hasAttachments ? [
            'message' => base64_encode(serialize($this->message)),
            'data' => base64_encode(serialize($this->data)),
            'hasAttachments' => true,
        ] : [
            'message' => $this->message,
            'data' => $this->data,
            'hasAttachments' => false,
        ];
    }

    /**
     * 从其序列化数据封送对象。
     */
    public function __unserialize(array $data)
    {
        if (isset($data['hasAttachments']) && $data['hasAttachments'] === true) {
            $this->message = unserialize(base64_decode($data['message']));
            $this->data = unserialize(base64_decode($data['data']));
        } else {
            $this->message = $data['message'];
            $this->data = $data['data'];
        }
    }
}
