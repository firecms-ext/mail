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

use Closure;
use FirecmsExt\Mail\PendingMail;
use FirecmsExt\Mail\SentMessage;

/**
 * 邮寄者.
 */
interface MailerInterface
{
    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function to(mixed $users): PendingMail;

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function bcc(mixed $users): PendingMail;

    /**
     * Send a new message with only a raw text part.
     */
    public function raw(string $text, mixed $callback): ?SentMessage;

    /**
     * Send a new message using a view.
     */
    public function send(array|string|MailableInterface $view, Closure|string $callback = null): mixed;
}
