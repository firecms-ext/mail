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

use FirecmsExt\Mail\SentMessage;
use Symfony\Component\Mime\Email;

/**
 * @property Email $message
 */
class MessageSent
{
    /**
     * 发送的消息。
     */
    public SentMessage $sent;

    /**
     * 消息数据。
     */
    public array $data;

    /**
     * 创建一个新的事件实例。
     */
    public function __construct(SentMessage $message, array $data = [])
    {
        $this->sent = $message;
        $this->data = $data;
    }

    /**
     * 获取对象的可序列化表示形式。
     *
     * @return array
     */
    public function __serialize()
    {
        $hasAttachments = collect($this->message->getAttachments())->isNotEmpty();

        return $hasAttachments ? [
            'sent' => base64_encode(serialize($this->sent)),
            'data' => base64_encode(serialize($this->data)),
            'hasAttachments' => true,
        ] : [
            'sent' => $this->sent,
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
            $this->sent = unserialize(base64_decode($data['sent']));
            $this->data = unserialize(base64_decode($data['data']));
        } else {
            $this->sent = $data['sent'];
            $this->data = $data['data'];
        }
    }

    /**
     * 动态获取原始消息。
     * @return mixed
     * @throws \Exception
     */
    public function __get($key)
    {
        if ($key === 'message') {
            return $this->sent->getOriginalMessage();
        }

        throw new \Exception('Unable to access undefined property on ' . __CLASS__ . ': ' . $key);
    }
}
