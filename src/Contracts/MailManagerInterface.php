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

/**
 * 邮件管理.
 */
interface MailManagerInterface
{
    /**
     * Get a mailer instance by name.
     */
    public function mailer(string $name = null): MailerInterface;
}
