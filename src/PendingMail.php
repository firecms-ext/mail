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

use FirecmsExt\Mail\Contracts\HasLocalePreferenceInterface;
use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailerInterface;
use Hyperf\Utils\Traits\Conditionable;

class PendingMail
{
    use Conditionable;

    /**
     * 邮寄者的实例。
     */
    protected MailerInterface $mailer;

    /**
     * 消息的区域设置。
     */
    protected string $locale;

    /**
     * 邮件的“收件人”。
     */
    protected array $to = [];

    /**
     * 邮件的“抄送人”。
     */
    protected array $cc = [];

    /**
     *  邮件的“秘密抄送人”。
     */
    protected array $bcc = [];

    /**
     * 创建一个新的可发送邮件实例。
     */
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * 设置消息的区域设置。
     *
     * @return $this
     */
    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * 设置邮件的收件人。
     *
     * @return $this
     */
    public function to(mixed $users): static
    {
        $this->to = $users;

        if (! $this->locale && $users instanceof HasLocalePreferenceInterface) {
            $this->locale($users->preferredLocale());
        }

        return $this;
    }

    /**
     * 设置邮件的抄送人。
     *
     * @return $this
     */
    public function cc(mixed $users): static
    {
        $this->cc = $users;

        return $this;
    }

    /**
     * 设置邮件的秘密抄送人。
     *
     * @return $this
     */
    public function bcc(mixed $users): static
    {
        $this->bcc = $users;

        return $this;
    }

    /**
     * 发送一个新的邮件消息实例。
     */
    public function send(MailableInterface $mailable): ?SentMessage
    {
        return $this->mailer->send($this->fill($mailable));
    }

    /**
     * 将给定的可邮件推入队列。
     */
    public function queue(MailableInterface $mailable): mixed
    {
        return $this->mailer->queue($this->fill($mailable));
    }

    /**
     * 在(n)秒后交付排队的消息。
     *
     * @return mixed
     */
    public function later(\DateInterval|\DateTimeInterface|int $delay, MailableInterface $mailable)
    {
        return $this->mailer->later($delay, $this->fill($mailable));
    }

    /**
     * 在可邮寄邮件中填写地址。
     */
    protected function fill(MailableInterface $mailable): Mailable
    {
        return tap($mailable->to($this->to)
            ->cc($this->cc)
            ->bcc($this->bcc), function (MailableInterface $mailable) {
                if ($this->locale) {
                    $mailable->locale($this->locale);
                }
            });
    }
}
