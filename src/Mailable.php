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
use FirecmsExt\Contract\HasMailAddress;
use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailerInterface;
use FirecmsExt\Mail\Contracts\MailManagerInterface;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Contract\CompressInterface;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\UnCompressInterface;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Str;
use Hyperf\Utils\Traits\ForwardsCalls;
use Hyperf\View\RenderInterface;
use League\Flysystem\FileNotFoundException;
use ReflectionClass;
use ReflectionProperty;

abstract class Mailable implements MailableInterface, CompressInterface, UnCompressInterface
{
    use ForwardsCalls;

    /**
     * 消息的区域设置。
     */
    public string $locale;

    /**
     * 留言的人。
     */
    public array $from;

    /**
     * 邮件的“收件人”。
     */
    public array $to = [];

    /**
     * 邮件的“抄送”收件人。
     */
    public array $cc = [];

    /**
     * 邮件的“密件抄送”收件人。
     */
    public array $bcc = [];

    /**
     * 邮件的“回复”收件人。
     */
    public array $replyTo;

    /**
     * 信息的主题。
     */
    public string $subject;

    /**
     * 用于消息的HTML视图。
     */
    public string $htmlViewTemplate;

    /**
     * 用于消息的纯文本视图。
     */
    public string $textViewTemplate;

    /**
     * 消息的视图数据。
     */
    public array $viewData = [];

    /**
     * 用于消息的HTML内容。
     */
    public string $htmlBody;

    /**
     * 用于消息的纯文本内容。
     */
    public string $textBody;

    /**
     * 邮件的附件。
     */
    public array $attachments = [];

    /**
     * The raw attachments for the message.
     */
    public array $rawAttachments = [];

    /**
     * The attachments from a storage disk.
     */
    public array $storageAttachments = [];

    /**
     * The priority of this message.
     */
    public int $priority = 3;

    /**
     * The name of the mailer that should send the message.
     */
    public string $mailer;

    /**
     * The callbacks for the message.
     *
     * @var \Closure[]
     */
    public array $callbacks = [];

    /**
     * The callback that should be invoked while building the view data.
     *
     * @var callable
     */
    public static $viewDataCallback;

    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function priority(int $level): static
    {
        $this->priority = $level;

        return $this;
    }

    public function from(HasMailAddress|string $address, ?string $name = null): static
    {
        $this->from = $this->normalizeRecipient($address, $name);

        return $this;
    }

    public function hasFrom($address, ?string $name = null): bool
    {
        return $this->from == $this->normalizeRecipient($address, $name);
    }

    public function replyTo(HasMailAddress|string $address, ?string $name = null): static
    {
        $this->replyTo = $this->normalizeRecipient($address, $name);

        return $this;
    }

    public function hasReplyTo($address, ?string $name = null): bool
    {
//        return $this->replyTo == $this->normalizeRecipient($address, $name);
        return $this->hasRecipient($address, $name, 'replyTo');
    }

    /**
     * @return $this|Mailable
     */
    public function to(HasMailAddress|array|string|Collection $address, ?string $name = null): static
    {
        return $this->addRecipient($address, $name, 'to');
    }

    public function hasTo(HasMailAddress|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'to');
    }

    public function cc(HasMailAddress|array|string|Collection $address, ?string $name = null): static
    {
        return $this->addRecipient($address, $name, 'cc');
    }

    public function hasCc(HasMailAddress|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'cc');
    }

    public function bcc(HasMailAddress|array|string|Collection $address, ?string $name = null): static
    {
        return $this->addRecipient($address, $name, 'bcc');
    }

    public function hasBcc(HasMailAddress|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'bcc');
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function attach(string $file, array $options = []): static
    {
        $this->attachments = collect($this->attachments)
            ->push(compact('file', 'options'))
            ->unique('file')
            ->all();

        return $this;
    }

    public function attachFromStorage(?string $adapter, string $path, ?string $name = null, array $options = []): static
    {
        $this->storageAttachments = collect($this->storageAttachments)->push([
            'storage' => $adapter ?: config('file.default'),
            'path' => $path,
            'name' => $name ?? basename($path),
            'options' => $options,
        ])->unique(function ($file) {
            return $file['name'] . $file['storage'] . $file['path'];
        })->all();

        return $this;
    }

    /**
     * Attach a file to the message from storage.
     *
     * @return $this
     */
    public function attachFromDefaultStorage(string $path, ?string $name = null, array $options = [])
    {
        return $this->attachFromStorage(null, $path, $name, $options);
    }

    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->rawAttachments = collect($this->rawAttachments)
            ->push(compact('data', 'name', 'options'))
            ->unique(function ($file) {
                return $file['name'] . $file['data'];
            })->all();

        return $this;
    }

    public function mailer(string $mailer): static
    {
        $this->mailer = $mailer;

        return $this;
    }

    /**
     * Register a callback to be called with the Swift message instance.
     *
     * @return $this
     */
    public function withSwiftMessage(Closure $callback): static
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called while building the view data.
     */
    public static function buildViewDataUsing(callable $callback): void
    {
        static::$viewDataCallback = $callback;
    }

    public function htmlView(string $template): static
    {
        $this->htmlViewTemplate = $template;

        return $this;
    }

    public function textView(string $template): static
    {
        $this->textViewTemplate = $template;

        return $this;
    }

    public function with(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } elseif (is_string($key)) {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    public function htmlBody(string $content): static
    {
        $this->htmlBody = $content;

        return $this;
    }

    public function textBody(string $content): static
    {
        $this->textBody = $content;

        return $this;
    }

    public function handler(Message $message): void
    {
        $mailable = clone $this;

        call([$mailable, 'build']);

        $data = $mailable->buildViewData();
        $data['message'] = $message;
        [$html, $plain] = $mailable->buildView($data);

        $mailable
            ->buildAddresses($message)
            ->buildSubject($message)
            ->runCallbacks($message)
            ->buildAttachments($message)
            ->buildContents($message, $html, $plain, $data);
    }

    public function render(MailerInterface|MailManagerInterface $mailer = null): string
    {
        $mailer = $this->resolveMailer($mailer);

        return $mailer->render($this);
    }

    public function send(MailerInterface|MailManagerInterface $mailer = null): array
    {
        $mailer = $this->resolveMailer($mailer);

        return $mailer->sendNow($this);
    }

    public function queue(?string $queue = null): bool
    {
        $queue = $queue ?: (property_exists($this, 'queue') ? $this->queue : array_key_first(config('async_queue')));

        return ApplicationContext::getContainer()->get(DriverFactory::class)->get($queue)->push($this->newQueuedJob());
    }

    public function later(int $delay, ?string $queue = null): bool
    {
        $queue = $queue ?: (property_exists($this, 'queue') ? $this->queue : array_key_first(config('async_queue')));

        return ApplicationContext::getContainer()->get(DriverFactory::class)->get($queue)->push($this->newQueuedJob(), $delay);
    }

    /**
     * @return static
     */
    public function uncompress(): CompressInterface
    {
        foreach ($this as $key => $value) {
            if ($value instanceof UnCompressInterface) {
                $this->{$key} = $value->uncompress();
            }
        }

        return $this;
    }

    /**
     * @return static
     */
    public function compress(): UnCompressInterface
    {
        foreach ($this as $key => $value) {
            if ($value instanceof CompressInterface) {
                $this->{$key} = $value->compress();
            }
        }

        return $this;
    }

    public function buildViewData(): array
    {
        $data = $this->viewData;

        if (static::$viewDataCallback) {
            $data = array_merge($data, call_user_func(static::$viewDataCallback, $this));
        }

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== self::class) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    protected function resolveMailer(MailerInterface|MailManagerInterface $mailer = null): MailerInterface
    {
        return empty($mailer)
            ? ApplicationContext::getContainer()->get(MailManagerInterface::class)->mailer($this->mailer)
            : ($mailer instanceof MailManager ? $mailer->mailer($this->mailer) : $mailer);
    }

    /**
     * Make the queued mailable job instance.
     */
    protected function newQueuedJob(): QueuedMailableJob
    {
        return new QueuedMailableJob($this);
    }

    /**
     * @return $this
     */
    protected function addRecipient(array|string|Collection $address, ?string $name = null, string $property = 'to'): static
    {
        $this->{$property} = array_merge($this->{$property}, $this->arrayizeAddress($address, $name));

        return $this;
    }

    /**
     * Convert the given recipient arguments to an array.
     */
    protected function arrayizeAddress(array|string|Collection $address, ?string $name = null): array
    {
        $addresses = [];
        if (is_array($address) or $address instanceof Collection) {
            foreach ($address as $item) {
                if (is_array($item) && isset($item['address'])) {
                    $addresses[] = [
                        'address' => $item['address'],
                        'name' => $item['name'] ?? null,
                    ];
                } elseif (is_string($item) or $item instanceof HasMailAddress) {
                    $addresses[] = $this->normalizeRecipient($item);
                }
            }
        } else {
            $addresses[] = $this->normalizeRecipient($address, $name);
        }
        return $addresses;
    }

    /**
     * Convert the given recipient into an object.
     */
    protected function normalizeRecipient(HasMailAddress|string $address, ?string $name = null): array
    {
        if ($address instanceof HasMailAddress) {
            $name = $address->getMailAddressDisplayName();
            $address = $address->getMailAddress();
        }

        return compact('address', 'name');
    }

    /**
     * Determine if the given recipient is set on the mailable.
     */
    protected function hasRecipient(object|array|string $address, ?string $name = null, string $property = 'to'): bool
    {
        $expected = $this->arrayizeAddress($address, $name)[0];

        $expected = [
            'name' => $expected['name'] ?? null,
            'address' => $expected['address'],
        ];

        return collect(in_array($property, ['replyTo', 'from']) ? [$this->{$property}] : $this->{$property})->contains(function ($actual) use ($expected) {
            if (! isset($expected['name'])) {
                return $actual['address'] == $expected['address'];
            }

            return $actual == $expected;
        });
    }

    protected function buildView(array $data): array
    {
        Coroutine::create(function () use ($data, &$html, &$plain) {
            if (! empty($this->locale)) {
                ApplicationContext::getContainer()->get(TranslatorInterface::class)->setLocale($this->locale);
            }

            $html = $plain = null;

            if (! empty($this->htmlBody)) {
                $html = $this->htmlBody;
            } elseif (! empty($this->htmlViewTemplate)) {
                $html = $this->renderView($this->htmlViewTemplate, $data);
            }

            if (! empty($this->textBody)) {
                $plain = $this->textBody;
            } elseif (! empty($this->textViewTemplate)) {
                $plain = $this->renderView($this->textViewTemplate, $data);
            }
        });

        return [$html, $plain];
    }

    /**
     * Render the given view.
     */
    protected function renderView(string $view, array $data): string
    {
        return ApplicationContext::getContainer()->get(RenderInterface::class)->getContents($view, $data);
    }

    /**
     * Add all of the addresses to the message.
     *
     * @return $this
     */
    protected function buildAddresses(Message $message): static
    {
        foreach (['from', 'replyTo'] as $type) {
            is_array($this->{$type}) && $message->{'set' . ucfirst($type)}($this->{$type}['address'], $this->{$type}['name']);
        }

        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{'set' . ucfirst($type)}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * Set the subject for the message.
     *
     * @return $this
     */
    protected function buildSubject(Message $message): static
    {
        if ($this->subject) {
            $message->setSubject($this->subject);
        } else {
            $message->setSubject(Str::title(Str::snake(class_basename($this), ' ')));
        }

        return $this;
    }

    /**
     * Add all of the attachments to the message.
     *
     * @return $this
     * @throws FileNotFoundException
     */
    protected function buildAttachments(Message $message): static
    {
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $message->attachData(
                $attachment['data'],
                $attachment['name'],
                $attachment['options']
            );
        }

        // Add all of the adapter attachments to the message.
        foreach ($this->storageAttachments as $attachment) {
            $storage = ApplicationContext::getContainer()->get(FilesystemFactory::class)->get($attachment['storage']);

            $message->attachData(
                $storage->read($attachment['path']),
                $attachment['name'] ?? basename($attachment['path']),
                array_merge(['mime' => $storage->getMimetype($attachment['path'])], $attachment['options'])
            );
        }

        return $this;
    }

    /**
     * Add the content to a given message.
     *
     * @return $this
     */
    protected function buildContents(Message $message, ?string $html, ?string $plain, array $data): static
    {
        if (! empty($html)) {
            $message->setBody($html, 'text/html');
        }

        if (! empty($plain)) {
            $method = empty($html) ? 'setBody' : 'addPart';

            $message->{$method}($plain ?: ' ', 'text/plain');
        }

        $message->setData($data);

        return $this;
    }

    /**
     * Run the callbacks for the message.
     *
     * @return $this
     */
    protected function runCallbacks(Message $message): static
    {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSwiftMessage());
        }

        return $this;
    }
}
