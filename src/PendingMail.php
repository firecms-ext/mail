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

use FirecmsExt\Contract\HasLocalePreference;
use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailerInterface;
use FirecmsExt\Mail\Contracts\MailManagerInterface;
use Hyperf\Utils\ApplicationContext;

class PendingMail
{
    /**
     * The mailer instance.
     */
    protected MailManager|Mailer $mailer;

    /**
     * The locale of the message.
     */
    protected string $locale;

    /**
     * The "to" recipients of the message.
     */
    protected array $to = [];

    /**
     * The "cc" recipients of the message.
     */
    protected array $cc = [];

    /**
     * The "bcc" recipients of the message.
     */
    protected array $bcc = [];

    /**
     * Create a new mailable mailer instance.
     */
    public function __construct(MailerInterface|MailManagerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Set the locale of the message.
     *
     * @return $this
     */
    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @return $this
     */
    public function to(mixed $users): static
    {
        $this->to = $users;

        if (! $this->locale
            && $users instanceof HasLocalePreference
            && is_string($locale = $users->getPreferredLocale())
        ) {
            $this->locale($locale);
        }

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @return $this
     */
    public function cc(mixed $users): static
    {
        $this->cc = $users;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @return $this
     */
    public function bcc(mixed $users): static
    {
        $this->bcc = $users;

        return $this;
    }

    /**
     * Set the mailer of the message.
     *
     * @return $this
     */
    public function mailer(string $name): static
    {
        $this->mailer = ApplicationContext::getContainer()->get(MailManagerInterface::class)->get($name);

        return $this;
    }

    /**
     * Render the given message as a view.
     */
    public function render(MailableInterface $mailable): string
    {
        return $this->mailer->render($this->fill($mailable));
    }

    /**
     * Send a new mailable message instance.
     */
    public function send(MailableInterface $mailable): array
    {
        return $this->mailer->send($this->fill($mailable));
    }

    /**
     * Push the given mailable onto the queue.
     */
    public function queue(MailableInterface $mailable, ?string $queue = null): bool
    {
        return $this->mailer->queue($this->fill($mailable), $queue);
    }

    /**
     * Deliver the queued message after the given delay.
     */
    public function later(MailableInterface $mailable, int $delay, ?string $queue = null): bool
    {
        return $this->mailer->later($this->fill($mailable), $delay, $queue);
    }

    /**
     * Populate the mailable with the addresses.
     */
    protected function fill(MailableInterface $mailable): MailableInterface
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
