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

use BadMethodCallException;
use DateInterval;
use DateTimeInterface;
use FirecmsExt\Mail\Contracts\AttachableInterface;
use FirecmsExt\Mail\Contracts\HtmlStringInterface;
use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailerInterface;
use FirecmsExt\Mail\Contracts\MailManagerInterface;
use FirecmsExt\Mail\Contracts\RenderInterface;
use FirecmsExt\Mail\Utils\HtmlString;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Macroable\Macroable;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Hyperf\Utils\Str;
use Hyperf\Utils\Traits\Conditionable;
use Hyperf\Utils\Traits\ForwardsCalls;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Assert as PHPUnit;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;

/**
 * 邮寄邮件类.
 */
abstract class Mailable implements MailableInterface
{
    use Conditionable,
        ForwardsCalls,
        Macroable {
        __call as macroCall;
    }

    /**
     * 消息的区域设置。
     */
    public string $locale;

    /**
     * 寄件地址
     */
    public array $from = [];

    /**
     * 收件人.
     */
    public array $to = [];

    /**
     * 抄送人.
     */
    public array $cc = [];

    /**
     * 秘密抄送人.
     */
    public array $bcc = [];

    /**
     * 回复收件人.
     */
    public array $replyTo = [];

    /**
     * 邮件主题.
     */
    public string $subject;

    /**
     * 邮件视图。
     */
    public string $view;

    /**
     * 纯文本视图。
     */
    public string $textView;

    /**
     * 邮件视图数据.
     */
    public array $viewData = [];

    /**
     * 邮件的附件.
     */
    public array $attachments = [];

    /**
     * 邮件的原始附件。
     */
    public array $rawAttachments = [];

    /**
     * 来自存储磁盘的附件。
     */
    public array $diskAttachments = [];

    /**
     * 邮件回调.
     */
    public array $callbacks = [];

    /**
     * 格式化消息时应使用的主题名称。
     */
    public ?string $theme;

    /**
     * 应该发送消息的邮件服务器的名称。
     */
    public string $mailer;

    /**
     * 在构建视图数据时应该调用的回调。
     *
     * @var callable
     */
    public static $viewDataCallback;

    /**
     * The HTML to use for the message.
     */
    protected string $html;

    /**
     * The tags for the message.
     */
    protected array $tags = [];

    /**
     * The metadata for the message.
     */
    protected array $metadata = [];

    /**
     * 为测试/断言呈现的可邮寄视图。
     */
    protected array $assertionableRenderStrings;

    /**
     * 动态地将参数绑定到消息。
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     *
     * @throws BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (str_starts_with($method, 'with')) {
            return $this->with(Str::camel(substr($method, 4)), $parameters[0]);
        }

        static::throwBadMethodCallException($method);
    }

    /**
     * 使用给定的邮件器发送消息。
     * @throws ReflectionException
     */
    public function send(MailerInterface|MailManagerInterface $mailer): ?SentMessage
    {
        $mailer = $mailer instanceof MailManagerInterface
            ? $mailer->mailer($this->mailer)
            : $mailer;

        return $mailer->send($this->buildView(), function ($message) {
            $this->buildFrom($message)
                ->buildRecipients($message)
                ->buildSubject($message)
                ->buildTags($message)
                ->buildMetadata($message)
                ->runCallbacks($message)
                ->buildAttachments($message);
        });
    }

    /**
     * 对要发送的消息进行排队。
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function queue(?string $queue = null): mixed
    {
        if (isset($this->delay)) {
            return $this->later($this->delay, $queue);
        }
        return $this->later(0, $queue);
    }

    /**
     * 在(n)秒后交付排队的消息。
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function later(DateInterval|DateTimeInterface|int $delay, ?string $queue = null): mixed
    {
        $queue = $queue ?: (property_exists($this, 'queue')
            ? $this->queue : array_key_first(config('async_queue', [])));

        if (is_null($queue)) {
            throw new \Exception('Please configure "async_queue"', 500);
        }

        return ApplicationContext::getContainer()
            ->get(DriverFactory::class)
            ->get($queue)
            ->push($this->newQueuedJob(), $delay);
    }

    /**
     * 为消息构建视图数据。
     *
     * @throws ReflectionException
     */
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

    /**
     * 设置此消息的优先级。
     *
     * 整数形式，1 优先级最高，5 优先级最低。
     *
     * @return $this
     */
    public function priority(int $level = 3): static
    {
        $this->callbacks[] = function ($message) use ($level) {
            $message->priority($level);
        };

        return $this;
    }

    /**
     * 设置邮件发送人。
     *
     * @return $this
     */
    public function from(object|array|string $address, string $name = null): static
    {
        return $this->setAddress($address, $name, 'from');
    }

    /**
     * 确定是否在可邮寄邮件上设置了给定的发件人。
     */
    public function hasFrom(object|array|string $address, string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'from');
    }

    /**
     * 设置邮件收件人。
     *
     * @return $this
     */
    public function to(object|array|string $address, ?string $name = null): static
    {
        return $this->setAddress($address, $name, 'to');
    }

    /**
     * 确定是否在可邮寄邮件上设置了给定的收件人。
     */
    public function hasTo(object|array|string $address, string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'to');
    }

    /**
     * 设置邮件抄送人。
     *
     * @return $this
     */
    public function cc(object|array|string $address, ?string $name = null): static
    {
        return $this->setAddress($address, $name, 'cc');
    }

    /**
     * 确定是否在可邮寄邮件上设置了给定的抄送人。
     */
    public function hasCc(object|array|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'cc');
    }

    /**
     * 设置邮件秘密抄送人。
     *
     * @return $this
     */
    public function bcc(object|array|string $address, ?string $name = null): static
    {
        return $this->setAddress($address, $name, 'bcc');
    }

    /**
     * 确定是否在可邮寄邮件上设置了给定的秘密抄送人。
     */
    public function hasBcc(object|array|string $address, string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'bcc');
    }

    /**
     * 设置邮件回复人。
     *
     * @return $this
     */
    public function replyTo(object|array|string $address, string $name = null): static
    {
        return $this->setAddress($address, $name, 'replyTo');
    }

    /**
     * 确定是否在可邮寄邮件上设置了给定的回复人。
     */
    public function hasReplyTo(object|array|string $address, string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'replyTo');
    }

    /**
     * 设置消息的主题。
     *
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * 确定可邮寄邮件是否有给定的主题。
     */
    public function hasSubject(string $subject): bool
    {
        return $this->subject === $subject;
    }

    /**
     * 为消息设置视图和视图数据。
     *
     * @return $this
     */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * 为消息设置呈现的 HTML 内容。
     *
     * @return $this
     */
    public function html(string $html): static
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Set the plain text view for the message.
     *
     * @return $this
     */
    public function text(string $textView, array $data = []): static
    {
        $this->textView = $textView;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * 设置消息的视图数据。
     *
     * @return $this
     */
    public function with(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * 在消息上附加一个文件。
     *
     * @return $this
     */
    public function attach(Attachment|string|AttachableInterface $file, array $options = []): static
    {
        if ($file instanceof AttachableInterface) {
            $file = $file->toMailAttachment();
        }

        if ($file instanceof Attachment) {
            return $file->attachTo($this);
        }

        $this->attachments = collect($this->attachments)
            ->push(compact('file', 'options'))
            ->unique('file')
            ->all();

        return $this;
    }

    /**
     * 将多个文件附加到消息。
     *
     * @return $this
     */
    public function attachMany(array $files): static
    {
        foreach ($files as $file => $options) {
            if (is_int($file)) {
                $this->attach($options);
            } else {
                $this->attach($file, $options);
            }
        }

        return $this;
    }

    /**
     * 将文件从存储器附加到消息。
     *
     * @return $this
     */
    public function attachFromStorage(string $path, string $name = null, array $options = []): static
    {
        return $this->attachFromStorageDisk(null, $path, $name, $options);
    }

    /**
     * 将文件从存储器附加到消息。
     *
     * @return $this
     */
    public function attachFromStorageDisk(?string $disk, string $path, string $name = null, array $options = []): static
    {
        $this->diskAttachments = collect($this->diskAttachments)->push([
            'disk' => $disk,
            'path' => $path,
            'name' => $name ?? basename($path),
            'options' => $options,
        ])->unique(function ($file) {
            return $file['name'] . $file['disk'] . $file['path'];
        })->all();

        return $this;
    }

    /**
     * 作为附件附加内存中的数据。
     *
     * @return $this
     */
    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->rawAttachments = collect($this->rawAttachments)
            ->push(compact('data', 'name', 'options'))
            ->unique(function ($file) {
                return $file['name'] . $file['data'];
            })->all();

        return $this;
    }

    /**
     * 当底层传输支持时，向消息添加标记头。
     *
     * @return $this
     */
    public function tag(string $value): static
    {
        $this->tags[] = $value;

        return $this;
    }

    /**
     * 在基础传输支持的情况下，向消息添加元数据头。
     *
     * @return $this
     */
    public function metadata(string $key, string $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * 断言给定的文本存在于HTML电子邮件主体中。
     * @return $this
     * @throws ReflectionException
     */
    public function assertSeeInHtml(string $string): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertTrue(
            str_contains($html, $string),
            "Did not see expected text [{$string}] within email body."
        );

        return $this;
    }

    /**
     * 断言给定的文本不在HTML电子邮件正文中。
     * @return $this
     * @throws ReflectionException
     */
    public function assertDontSeeInHtml(string $string): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertFalse(
            str_contains($html, $string),
            "Saw unexpected text [{$string}] within email body."
        );

        return $this;
    }

    /**
     * 断言给定的文本字符串在HTML电子邮件正文中是按顺序出现的。
     *
     * @return $this
     * @throws ReflectionException
     */
    public function assertSeeInOrderInHtml(array $strings): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertThat($strings, new SeeInOrder($html));

        return $this;
    }

    /**
     * 断言给定的文本存在于纯文本电子邮件正文中。
     * @param string $string
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function assertSeeInText(string $string): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertTrue(
            str_contains($text, $string),
            "Did not see expected text [{$string}] within text email body."
        );

        return $this;
    }

    /**
     * 断言给定的文本在纯文本电子邮件正文中不存在。
     * @param string $string
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function assertDontSeeInText(string $string): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertFalse(
            str_contains($text, $string),
            "Saw unexpected text [{$string}] within text email body."
        );

        return $this;
    }

    /**
     * 断言给定的文本字符串按顺序出现在纯文本电子邮件正文中。
     * @param array $strings
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function assertSeeInOrderInText(array $strings): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertThat($strings, new SeeInOrder($text));

        return $this;
    }

    /**
     * 设置应该发送邮件的邮件服务器的名称。
     * @return $this
     */
    public function mailer(string $mailer): static
    {
        $this->mailer = $mailer;

        return $this;
    }

    /**
     * 用 Symfony 消息实例注册要调用的回调。
     *
     * @return $this
     */
    public function withSymfonyMessage(callable $callback): static
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * 注册一个要在构建视图数据时调用的回调。
     */
    public static function buildViewDataUsing(callable $callback)
    {
        static::$viewDataCallback = $callback;
    }

    /**
     * 使排队的作业实例可邮寄。
     */
    protected function newQueuedJob(): QueuedMailableJob
    {
        return new QueuedMailableJob($this);
    }

    /**
     * 为消息构建视图。
     */
    protected function buildView(): array|string
    {
        if (isset($this->html)) {
            return array_filter([
                'html' => new HtmlString($this->html),
                'text' => $this->textView ?? null,
            ]);
        }

        if (isset($this->view, $this->textView)) {
            return [$this->view, $this->textView];
        }

        if (isset($this->textView)) {
            return ['text' => $this->textView];
        }

        return $this->view;
    }

    /**
     * 将发送者添加到消息中。
     * @return $this
     */
    protected function buildFrom(Message $message): static
    {
        if (! empty($this->from)) {
            $message->from($this->from[0]['address'], $this->from[0]['name']);
        }

        return $this;
    }

    /**
     * 将所有收件人添加到邮件中。
     *
     * @return $this
     */
    protected function buildRecipients(Message $message): static
    {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * 设置消息的主题。
     *
     * @return $this
     */
    protected function buildSubject(Message $message): static
    {
        if ($this->subject) {
            $message->subject($this->subject);
        } else {
            $message->subject(Str::title(Str::snake(class_basename($this), ' ')));
        }

        return $this;
    }

    /**
     * 将所有附件添加到消息中。
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws FilesystemException
     * @throws NotFoundExceptionInterface
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

        $this->buildDiskAttachments($message);

        return $this;
    }

    /**
     * 将所有磁盘附件添加到消息中。
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws FilesystemException
     */
    protected function buildDiskAttachments(Message $message)
    {
        foreach ($this->diskAttachments as $attachment) {
            $storage = ApplicationContext::getContainer()
                ->get(FilesystemFactory::class)
                ->get($attachment['storage']);

            $message->attachData(
                $storage->read($attachment['path']),
                $attachment['name'] ?? basename($attachment['path']),
                array_merge(['mime' => $storage->getMimetype($attachment['path'])], $attachment['options'])
            );
        }
    }

    /**
     * 将所有定义的标记添加到消息中。
     * @return $this
     */
    protected function buildTags(Message $message): static
    {
        if ($this->tags) {
            foreach ($this->tags as $tag) {
                $message->getHeaders()->add(new TagHeader($tag));
            }
        }

        return $this;
    }

    /**
     * 将所有定义的元数据添加到消息中。
     *
     * @return $this
     */
    protected function buildMetadata(Message $message): static
    {
        if ($this->metadata) {
            foreach ($this->metadata as $key => $value) {
                $message->getHeaders()->add(new MetadataHeader($key, $value));
            }
        }

        return $this;
    }

    /**
     * 运行消息的回调。
     *
     * @return $this
     */
    protected function runCallbacks(Message $message): static
    {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSymfonyMessage());
        }

        return $this;
    }

    /**
     * 设置邮件的收件人。
     *
     * 所有收件人在内部存储为 [['name' => ?, 'address' => ?]]
     *
     * @return $this
     */
    protected function setAddress(object|array|string $address, string $name = null, string $property = 'to'): static
    {
        if (empty($address)) {
            return $this;
        }

        foreach ($this->addressesToArray($address, $name) as $recipient) {
            $recipient = $this->normalizeRecipient($recipient);

            $this->{$property}[] = [
                'name' => $recipient->name ?? null,
                'address' => $recipient->email,
            ];
        }

        return $this;
    }

    /**
     * 将给定的收件人参数转换为数组。
     */
    protected function addressesToArray(object|array|string $address, ?string $name): Collection|array|string
    {
        if (! is_array($address) && ! $address instanceof Collection) {
            $address = is_string($name) ? [['name' => $name, 'email' => $address]] : [$address];
        }

        return $address;
    }

    /**
     * 将给定的收件人转换为对象。
     */
    protected function normalizeRecipient(mixed $recipient): object
    {
        if (is_array($recipient)) {
            if (array_values($recipient) === $recipient) {
                return (object) array_map(function ($email) {
                    return compact('email');
                }, $recipient);
            }
            return (object) $recipient;
        }
        if (is_string($recipient)) {
            return (object) ['email' => $recipient];
        }
        if ($recipient instanceof Address) {
            return (object) ['email' => $recipient->getAddress(), 'name' => $recipient->getName()];
        }

        return $recipient;
    }

    /**
     * 确定是否在可邮寄邮件上设置了给定的收件人。
     */
    protected function hasRecipient(object|array|string $address, string $name = null, string $property = 'to'): bool
    {
        if (empty($address)) {
            return false;
        }

        $expected = $this->normalizeRecipient(
            $this->addressesToArray($address, $name)[0]
        );

        $expected = [
            'name' => $expected->name ?? null,
            'address' => $expected->email,
        ];

        return collect($this->{$property})->contains(function ($actual) use ($expected) {
            if (! isset($expected['name'])) {
                return $actual['address'] == $expected['address'];
            }

            return $actual == $expected;
        });
    }

    /**
     * 将可邮寄的 HTML 和纯文本版本呈现到断言的视图中。
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    protected function renderForAssertions(): array
    {
        if ($this->assertionableRenderStrings) {
            return $this->assertionableRenderStrings;
        }

        $view = $this->buildView();
        $html = $this->render();

        if (is_array($view) && isset($view[1])) {
            $text = $view[1];
        }

        $text ??= $view['text'] ?? '';

        if (! empty($text) && ! $text instanceof HtmlStringInterface) {
            $text = $this->render();
        }

        return $this->assertionableRenderStrings = [(string) $html, (string) $text];
    }
}
