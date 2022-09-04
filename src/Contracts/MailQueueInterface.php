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
namespace FirecmsExt\Mail\Contracts;

interface MailQueueInterface
{
    /**
     * 对要发送的新电子邮件进行排队。
     */
    public function queue(MailableInterface $mailable, string $queue = null): mixed;

    /**
     * 等待(n)秒后发送的新电子邮件。
     */
    public function later(\DateInterval|\DateTimeInterface|int $delay, array|string|MailableInterface $view, string $queue = null): mixed;
}
