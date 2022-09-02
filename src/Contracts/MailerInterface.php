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

interface MailerInterface
{
    /**
     * 将给定的消息呈现为视图。
     */
    public function render(MailableInterface $mailable): string;

    /**
     * 使用可邮寄实例发送新消息，并返回失败的收件人。
     */
    public function sendNow(MailableInterface $mailable): ?array;

    /**
     * 使用可邮寄实例发送新消息，并返回失败的收件人。
     */
    public function send(MailableInterface $mailable);

    /**
     * 对要发送的新电子邮件进行排队。
     */
    public function queue(MailableInterface $mailable, ?string $queue = null): bool;

    /**
     * 对要发送的新电子邮件进行排队。
     */
    public function later(MailableInterface $mailable, int $delay, ?string $queue = null): bool;
}
