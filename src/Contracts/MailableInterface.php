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

use FirecmsExt\Mail\SentMessage;

/**
 * 邮件.
 */
interface MailableInterface
{
    /**
     * Send the message using the given mailer.
     */
    public function send(MailerInterface $mailer): ?SentMessage;

    /**
     * Queue the given message.
     */
    public function queue(?string $queue = null): mixed;

    /**
     * Deliver the queued message after (n) seconds.
     */
    public function later(\DateInterval|\DateTimeInterface|int $delay, ?string $queue = null): mixed;

    /**
     * Set the recipients of the message.
     *
     * @return $this
     */
    public function cc(object|array|string $address, ?string $name = null): static;

    /**
     * Set the recipients of the message.
     *
     * @return $this
     */
    public function bcc(object|array|string $address, ?string $name = null): static;

    /**
     * Set the recipients of the message.
     *
     * @return $this
     */
    public function to(object|array|string $address, ?string $name = null): static;

    /**
     * Set the name of the mailer that should be used to send the message.
     *
     * @return $this
     */
    public function mailer(string $mailer): static;
}
