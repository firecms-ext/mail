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
namespace FirecmsExt\Mail\Concerns;

use FirecmsExt\Mail\PendingMail;

trait PendingMailable
{
    public function to(mixed $users): PendingMail
    {
        return (new PendingMail($this))->to($users);
    }

    public function cc(mixed $users): PendingMail
    {
        return (new PendingMail($this))->cc($users);
    }

    public function bcc(mixed $users): PendingMail
    {
        return (new PendingMail($this))->bcc($users);
    }

    public function locale(string $locale): PendingMail
    {
        return (new PendingMail($this))->locale($locale);
    }

    public function mailer(string $name): PendingMail
    {
        return (new PendingMail($this))->mailer($name);
    }
}
