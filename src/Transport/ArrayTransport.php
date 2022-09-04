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
namespace FirecmsExt\Mail\Transport;

use Hyperf\Utils\Collection;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class ArrayTransport implements TransportInterface
{
    /**
     * Symfony消息的集合。
     */
    protected Collection $messages;

    /**
     * 创建一个新的阵列传输实例。
     */
    public function __construct()
    {
        $this->messages = new Collection();
    }

    /**
     * 获取传输的字符串表示形式。
     */
    public function __toString(): string
    {
        return 'array';
    }

    /**
     * {@inheritdoc}
     */
    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        return $this->messages[] = new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    /**
     * 检索消息集合。
     */
    public function messages(): Collection
    {
        return $this->messages;
    }

    /**
     * 清除本地集合中的所有消息。
     */
    public function flush(): Collection
    {
        return $this->messages = new Collection();
    }
}
