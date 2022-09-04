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

use Closure;
use FirecmsExt\Mail\Contracts\HtmlStringInterface;
use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailerInterface;
use FirecmsExt\Mail\Contracts\MailQueueInterface;
use FirecmsExt\Mail\Contracts\ShouldQueueInterface;
use FirecmsExt\Mail\Events\MessageSending;
use FirecmsExt\Mail\Events\MessageSent;
use FirecmsExt\Mail\Utils\HtmlString;
use Hyperf\AsyncQueue\JobInterface;
use Hyperf\Macroable\Macroable;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class Mailer implements MailerInterface, MailQueueInterface
{
    use Macroable;

    /**
     * 为邮件服务器配置的名称。
     */
    protected string $name;

    /**
     * Symfony Transport 实例。
     */
    protected TransportInterface $transport;

    /**
     * 事件调度程序实例。
     */
    protected EventDispatcherInterface $events;

    /**
     * 全局发送"地址"和"名称".
     */
    protected array $from;

    /**
     * 全局回复"地址"和"名称"。
     */
    protected array $replyTo;

    /**
     * 全局返回"地址"。
     */
    protected array $returnPath;

    /**
     * 全局邮寄"地址"和"名称".
     */
    protected array $to;

    /**
     * 队列工厂实现。
     */
    protected ?JobInterface $queue;

    /**
     * 创建一个新的 Mailer 实例。
     */
    public function __construct(
        string                   $name,
        TransportInterface       $transport,
        EventDispatcherInterface $events
    )
    {
        $this->name = $name;
        $this->transport = $transport;
        $this->events = $events;
    }

    /**
     * 设置全局发送"地址"和"名称".
     */
    public function alwaysFrom(string $address, ?string $name = null): void
    {
        $this->from = compact('address', 'name');
    }

    /**
     * 设置全局回复"地址"和"名称".
     */
    public function alwaysReplyTo(string $address, ?string $name = null): void
    {
        $this->replyTo = compact('address', 'name');
    }

    /**
     * 设置全局返回"地址"。
     */
    public function alwaysReturnPath(string $address): void
    {
        $this->returnPath = compact('address');
    }

    /**
     * 设置全局邮寄"地址"和"名称".
     */
    public function alwaysTo(string $address, string $name = null)
    {
        $this->to = compact('address', 'name');
    }

    /**
     * 开始发送可邮寄类实例的过程。
     */
    public function to(mixed $users): PendingMail
    {
        return (new PendingMail($this))->to($users);
    }

    /**
     * 开始发送可邮寄类实例的过程（抄送）。
     */
    public function cc(mixed $users): PendingMail
    {
        return (new PendingMail($this))->cc($users);
    }

    /**
     * 开始发送可邮寄类实例的过程（加密抄送）。
     */
    public function bcc(mixed $users): PendingMail
    {
        return (new PendingMail($this))->bcc($users);
    }

    /**
     * 发送只有 HTML 部分的新消息。
     */
    public function html(string $html, mixed $callback): ?SentMessage
    {
        return $this->send(['html' => new HtmlString($html)], [], $callback);
    }

    /**
     * 发送只有原始文本部分的新消息。
     */
    public function raw(string $text, mixed $callback): ?SentMessage
    {
        return $this->send(['raw' => $text], [], $callback);
    }

    /**
     * 发送一条只有普通部分的新信息。
     */
    public function plain(string $view, array $data, mixed $callback): ?SentMessage
    {
        return $this->send(['text' => $view], $data, $callback);
    }

    /**
     * 使用视图发送新消息。
     * @throws TransportExceptionInterface
     */
    public function send(array|string|MailableInterface $view, array $data = [], Closure|string $callback = null): bool|SentMessage|null
    {
        if ($view instanceof MailableInterface) {
            return $this->sendMailable($view);
        }

        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        // Once we have retrieved the view content for the e-mail we will set the body
        // of this message using the HTML type, which will provide a simple wrapper
        // to creating view based emails that are able to receive arrays of data.
        if (! is_null($callback)) {
            $callback($message);
        }

        $this->addContent($message, $view, $plain, $raw, $data);

        // If a global "to" address has been set, we will set that address on the mail
        // message. This is primarily useful during local development in which each
        // message should be delivered into a single mail address for inspection.
        if (isset($this->to['address'])) {
            $this->setGlobalToAndRemoveCcAndBcc($message);
        }

        // Next we will determine if the message should be sent. We give the developer
        // one final chance to stop this message and then we will send it to all of
        // its recipients. We will then fire the sent event for the sent message.
        $symfonyMessage = $message->getSymfonyMessage();

        if ($this->shouldSendMessage($symfonyMessage, $data)) {
            $symfonySentMessage = $this->sendSymfonyMessage($symfonyMessage);

            if ($symfonySentMessage) {
                $sentMessage = new SentMessage($symfonySentMessage);

                $this->dispatchSentEvent($sentMessage, $data);

                return $sentMessage;
            }
        }
        return null;
    }

    /**
     * 对要发送的新电子邮件进行排队。
     *
     * @throws InvalidArgumentException
     */
    public function queue(MailableInterface $mailable, ?string $queue = null): bool
    {
        return $mailable->mailer($this->name)->queue($queue);
    }

    /**
     * 等待(n)秒后发送的新电子邮件。
     *
     * @param MailableInterface $view
     * @param null|string $queue
     *
     * @throws InvalidArgumentException
     */
    public function later(\DateInterval|\DateTimeInterface|int $delay, $view, $queue = null): mixed
    {
        if (! $view instanceof MailableInterface) {
            throw new InvalidArgumentException('Only mailables may be queued.');
        }

        return $view->mailer($this->name)->later(
            $delay,
            is_null($queue) ? $this->queue : $queue
        );
    }

    /**
     * 在给定队列中等待(n)秒后发送新电子邮件。
     */
    public function laterOn(string $queue, \DateInterval|\DateTimeInterface|int $delay, MailableInterface $view): mixed
    {
        return $this->later($delay, $view, $queue);
    }

    /**
     * 获取 Symfony Transport 实例。
     */
    public function getSymfonyTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * 设置Symfony Transport实例。
     */
    public function setSymfonyTransport(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * 发送给定的可邮寄邮件。
     */
    protected function sendMailable(MailableInterface $mailable): SentMessage|bool|null
    {
        return $mailable instanceof ShouldQueueInterface
            ? $mailable->mailer($this->name)->queue()
            : $mailable->mailer($this->name)->send($this);
    }

    /**
     * 解析给定的视图名称或数组。
     *
     * @throws InvalidArgumentException
     */
    protected function parseView(array|string $view): array
    {
        if (is_string($view)) {
            return [$view, null, null];
        }

        // If the given view is an array with numeric keys, we will just assume that
        // both a "pretty" and "plain" view were provided, so we will return this
        // array as is, since it should contain both views with numerical keys.
        if (is_array($view) && isset($view[0])) {
            return [$view[0], $view[1], null];
        }

        // If this view is an array but doesn't contain numeric keys, we will assume
        // the views are being explicitly specified and will extract them via the
        // named keys instead, allowing the developers to use one or the other.
        if (is_array($view)) {
            return [
                    $view['html'] ?? null,
                    $view['text'] ?? null,
                    $view['raw'] ?? null,
            ];
        }

        throw new InvalidArgumentException('Invalid view.');
    }

    /**
     * 将内容添加到给定消息中。
     */
    protected function addContent(Message $message, null|string|object $view, ?string $plain, ?string $raw, ?array $data)
    {
        if (isset($view)) {
            $message->html($this->renderView($view, $data) ?: ' ');
        }

        if (isset($plain)) {
            $message->text($this->renderView($plain, $data) ?: ' ');
        }

        if (isset($raw)) {
            $message->text($raw);
        }
    }

    /**
     * 渲染给定的视图。
     */
    protected function renderView(string|object $view): string
    {
        return $view instanceof HtmlStringInterface
            ? $view->toHtml()
            : $view;
    }

    /**
     * 设置给定消息的全局“to”地址。
     */
    protected function setGlobalToAndRemoveCcAndBcc(Message $message)
    {
        $message->forgetTo();

        $message->to($this->to['address'], $this->to['name'], true);

        $message->forgetCc();
        $message->forgetBcc();
    }

    /**
     * 创建一个新的消息实例。
     */
    protected function createMessage(): Message
    {
        $message = new Message(new Email());

        // If a global from address has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and push this address.
        if (! empty($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        // When a global reply address was specified we will set this on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We will just go ahead and push this address.
        if (! empty($this->replyTo['address'])) {
            $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        if (! empty($this->returnPath['address'])) {
            $message->returnPath($this->returnPath['address']);
        }

        return $message;
    }

    /**
     * 发送一个 Symfony Email 实例。
     * @throws TransportExceptionInterface
     */
    protected function sendSymfonyMessage(Email $message): ?\Symfony\Component\Mailer\SentMessage
    {
        return $this->transport->send($message, Envelope::create($message));
    }

    /**
     * 确定邮件是否可以发送。
     */
    protected function shouldSendMessage(Email $message, array $data = []): bool
    {
        return $this->events->dispatch(new MessageSending($message, $data)) != false;
    }

    /**
     * 调度消息发送事件。
     */
    protected function dispatchSentEvent(SentMessage $message, array $data = [])
    {
        $this->events?->dispatch(
            new MessageSent($message, $data)
        );
    }
}
