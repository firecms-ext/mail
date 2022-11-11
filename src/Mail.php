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
namespace FirecmsExt\Mail;

use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailManagerInterface;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @method static PendingMail to(mixed $users)
 * @method static PendingMail cc(mixed $users)
 * @method static PendingMail bcc(mixed $users)
 * @method static bool later(MailableInterface $mailable, int $delay, ?string $queue = null)
 * @method static bool queue(MailableInterface $mailable, ?string $queue = null)
 * @method static null|int send(MailableInterface $mailable)
 *
 * @see \FirecmsExt\Mail\MailManager
 */
abstract class Mail
{
    /**
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getManager();

        return $instance->{$method}(...$args);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function mailer(string $name): PendingMail
    {
        return new PendingMail(static::getManager()->get($name));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function getManager(): ?MailManagerInterface
    {
        return ApplicationContext::getContainer()->get(MailManagerInterface::class);
    }
}
