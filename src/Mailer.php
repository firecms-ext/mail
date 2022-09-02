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

use FirecmsExt\Contract\ShouldQueue;
use FirecmsExt\Mail\Concerns\PendingMailable;
use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailerInterface;
use FirecmsExt\Mail\Events\MailMessageSending;
use FirecmsExt\Mail\Events\MailMessageSent;
use Hyperf\Macroable\Macroable;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swift_Mailer;
use Swift_Message;

class Mailer implements MailerInterface
{
    use Macroable;
    use PendingMailable;

    /**
     * The name that is configured for the mailer.
     */
    protected string $name;

    /**
     * The Swift Mailer instance.
     */
    protected Swift_Mailer $swift;

    /**
     * The event dispatcher instance.
     */
    protected ?EventDispatcherInterface $eventDispatcher;

    /**
     * The global from address and name.
     */
    protected array $from;

    /**
     * The global reply-to address and name.
     */
    protected array $replyTo;

    /**
     * The global return path address.
     */
    protected array $returnPath;

    /**
     * The global to address and name.
     */
    protected array $to;

    /**
     * Array of failed recipients.
     */
    protected array $failedRecipients = [];

    protected ContainerInterface $container;

    /**
     * Create a new Mailer instance.
     */
    public function __construct(
        string $name,
        Swift_Mailer $swift,
        ContainerInterface $container
    ) {
        $this->name = $name;
        $this->swift = $swift;
        $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        $this->container = $container;
    }

    /**
     * Set the global from address and name.
     *
     * @return $this
     */
    public function setAlwaysFrom(string $address, ?string $name = null): static
    {
        $this->from = compact('address', 'name');

        return $this;
    }

    /**
     * Set the global reply-to address and name.
     *
     * @return $this
     */
    public function setAlwaysReplyTo(string $address, ?string $name = null): static
    {
        $this->replyTo = compact('address', 'name');

        return $this;
    }

    /**
     * Set the global return path address.
     *
     * @return $this
     */
    public function setAlwaysReturnPath(string $address): static
    {
        $this->returnPath = compact('address');

        return $this;
    }

    /**
     * Set the global to address and name.
     *
     * @return $this
     */
    public function astAlwaysTo(string $address, ?string $name = null): static
    {
        $this->to = compact('address', 'name');

        return $this;
    }

    public function render(MailableInterface $mailable): string
    {
        $message = $this->createMessage();
        $mailable->handler($message);

        return $message->getBody();
    }

    public function sendNow(MailableInterface $mailable): ?array
    {
        $message = $this->createMessage();

        $mailable->handler($message);

        $data = $message->getData();

        // If a global "to" address has been set, we will set that address on the mail
        // message. This is primarily useful during local development in which each
        // message should be delivered into a single mail address for inspection.
        if (isset($this->to['address'])) {
            $this->setGlobalToAndRemoveCcAndBcc($message);
        }

        // Next we will determine if the message should be sent. We give the developer
        // one final chance to stop this message and then we will send it to all of
        // its recipients. We will then fire the sent event for the sent message.
        $swiftMessage = $message->getSwiftMessage();

        $this->eventDispatcher->dispatch(new MailMessageSending($swiftMessage, $data));

        $this->sendSwiftMessage($swiftMessage, $failedRecipients);

        $this->eventDispatcher->dispatch(new MailMessageSent($swiftMessage, $data));

        return $failedRecipients;
    }

    public function send(MailableInterface $mailable): array
    {
        return $mailable instanceof ShouldQueue
            ? $mailable->mailer($this->name)->queue()
            : $mailable->mailer($this->name)->send($this);
    }

    public function queue(MailableInterface $mailable, ?string $queue = null): bool
    {
        return $mailable->mailer($this->name)->queue($queue);
    }

    public function later(MailableInterface $mailable, int $delay, ?string $queue = null): bool
    {
        return $mailable->mailer($this->name)->later($delay, $queue);
    }

    /**
     * Get the Swift Mailer instance.
     */
    public function getSwiftMailer(): Swift_Mailer
    {
        return $this->swift;
    }

    /**
     * Set the Swift Mailer instance.
     */
    public function setSwiftMailer(Swift_Mailer $swift)
    {
        $this->swift = $swift;
    }

    /**
     * Set the global "to" address on the given message.
     */
    protected function setGlobalToAndRemoveCcAndBcc(Message $message)
    {
        $message->setTo($this->to['address'], $this->to['name']);
        $message->setCc(null, null, true);
        $message->setBcc(null, null, true);
    }

    /**
     * Create a new message instance.
     */
    protected function createMessage(): Message
    {
        $message = new Message($this->swift->createMessage('message'));

        // If a global from address has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and push this address.
        if (! empty($this->from['address'])) {
            $message->setFrom($this->from['address'], $this->from['name']);
        }

        // When a global reply address was specified we will set this on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We will just go ahead and push this address.
        if (! empty($this->replyTo['address'])) {
            $message->setReplyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        if (! empty($this->returnPath['address'])) {
            $message->setReturnPath($this->returnPath['address']);
        }

        return $message;
    }

    /**
     * Send a Swift Message instance.
     */
    protected function sendSwiftMessage(Swift_Message $message, ?array &$failedRecipients = null): ?int
    {
        $this->failedRecipients = [];

        try {
            return $this->swift->send($message, $failedRecipients);
        } finally {
            $this->forceReconnection();
        }
    }

    /**
     * Force the transport to re-connect.
     *
     * This will prevent errors in daemon queue situations.
     */
    protected function forceReconnection()
    {
        $this->getSwiftMailer()->getTransport()->stop();
    }
}
