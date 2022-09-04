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

use FirecmsExt\Mail\Contracts\AttachableInterface;
use Hyperf\Utils\Str;
use Hyperf\Utils\Traits\ForwardsCalls;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @mixin Email
 */
class Message
{
    use ForwardsCalls;

    /**
     * Symfony Email 实例。
     */
    protected Email $message;

    /**
     * 创建一个新的消息实例。
     */
    public function __construct(Email $message)
    {
        $this->message = $message;
    }

    /**
     * 动态地向 Symfony 实例传递缺失的方法。
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->message, $method, $parameters);
    }

    /**
     * 在消息中添加一个 “from” 地址。
     *
     * @return $this
     */
    public function from(array|string $address, string $name = null): static
    {
        is_array($address)
            ? $this->message->from(...$address)
            : $this->message->from(new Address($address, (string) $name));

        return $this;
    }

    /**
     * 设置邮件的“发送人”。
     *
     * @return $this
     */
    public function sender(array|string $address, string $name = null): static
    {
        is_array($address)
            ? $this->message->sender(...$address)
            : $this->message->sender(new Address($address, (string) $name));

        return $this;
    }

    /**
     * 设置消息的“返回路径”。
     *
     * @return $this
     */
    public function returnPath(string $address): static
    {
        $this->message->returnPath($address);

        return $this;
    }

    /**
     * 添加收件人地址。
     * @return $this
     */
    public function to(array|string $address, string $name = null, bool $override = false): static
    {
        if ($override) {
            is_array($address)
                ? $this->message->to(...$address)
                : $this->message->to(new Address($address, (string) $name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'To');
    }

    /**
     * 删除所有的收件人地址。
     *
     * @return $this
     */
    public function forgetTo(): static
    {
        if ($header = $this->message->getHeaders()->get('To')) {
            $this->addAddressDebugHeader('X-To', $this->message->getTo());

            $header->setAddresses([]);
        }

        return $this;
    }

    /**
     * 给消息添加抄送地址。
     *
     * @return $this
     */
    public function cc(array|string $address, string $name = null, bool $override = false): static
    {
        if ($override) {
            is_array($address)
                ? $this->message->cc(...$address)
                : $this->message->cc(new Address($address, (string) $name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'Cc');
    }

    /**
     * 删除所有抄送地址。
     *
     * @return $this
     */
    public function forgetCc(): static
    {
        if ($header = $this->message->getHeaders()->get('Cc')) {
            $this->addAddressDebugHeader('X-Cc', $this->message->getCC());

            $header->setAddresses([]);
        }

        return $this;
    }

    /**
     * 添加秘密抄送
     *
     * @return $this
     */
    public function bcc(array|string $address, string $name = null, bool $override = false): static
    {
        if ($override) {
            is_array($address)
                ? $this->message->bcc(...$address)
                : $this->message->bcc(new Address($address, (string) $name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'Bcc');
    }

    /**
     * 删除所以秘密抄送人.
     *
     * @return $this
     */
    public function forgetBcc(): static
    {
        if ($header = $this->message->getHeaders()->get('Bcc')) {
            $this->addAddressDebugHeader('X-Bcc', $this->message->getBcc());

            $header->setAddresses([]);
        }

        return $this;
    }

    /**
     * 添加回复地址
     *
     * @return $this
     */
    public function replyTo(array|string $address, string $name = null): static
    {
        return $this->addAddresses($address, $name, 'ReplyTo');
    }

    /**
     * 邮件主题.
     *
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->message->subject($subject);

        return $this;
    }

    /**
     * 邮件优先级.
     *
     * @return $this
     */
    public function priority(int $level): static
    {
        $this->message->priority($level);

        return $this;
    }

    /**
     * 邮件附件.
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

        $this->message->attachFromPath($file, $options['as'] ?? null, $options['mime'] ?? null);

        return $this;
    }

    /**
     * 作为附件附加内存中的数据。
     *
     * @return $this
     */
    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->message->attach($data, $name, $options['mime'] ?? null);

        return $this;
    }

    /**
     * 在消息中嵌入一个文件并获取CID。
     */
    public function embed(Attachment|string|AttachableInterface $file): string
    {
        if ($file instanceof AttachableInterface) {
            $file = $file->toMailAttachment();
        }

        if ($file instanceof Attachment) {
            return $file->attachWith(
                function ($path) use ($file) {
                    $cid = $file->as ?? Str::random();

                    $this->message->embedFromPath($path, $cid, $file->mime);

                    return "cid:{$cid}";
                },
                function ($data) use ($file) {
                    $this->message->embed($data(), $file->as, $file->mime);

                    return "cid:{$file->as}";
                }
            );
        }

        $cid = Str::random(10);

        $this->message->embedFromPath($file, $cid);

        return "cid:{$cid}";
    }

    /**
     * 在消息中嵌入内存数据并获取CID。
     */
    public function embedData(string $data, string $name, string $contentType = null): string
    {
        $this->message->embed($data, $name, $contentType);

        return "cid:{$name}";
    }

    /**
     * 获取底层Symfony Email实例。
     */
    public function getSymfonyMessage(): Email
    {
        return $this->message;
    }

    /**
     * 向邮件中添加收件人。
     * @return $this
     */
    protected function addAddresses(array|string $address, ?string $name, string $type): static
    {
        if (is_array($address)) {
            $type = lcfirst($type);

            $addresses = collect($address)->map(function ($address, $key) {
                if (is_string($key) && is_string($address)) {
                    return new Address($key, $address);
                }

                if (is_array($address)) {
                    return new Address($address['email'] ?? $address['address'], $address['name'] ?? null);
                }

                if (is_null($address)) {
                    return new Address($key);
                }

                return $address;
            })->all();

            $this->message->{"{$type}"}(...$addresses);
        } else {
            $this->message->{"add{$type}"}(new Address($address, (string) $name));
        }

        return $this;
    }

    /**
     * 为收件人列表添加地址调试头。
     *
     * @param Address[] $addresses
     * @return $this
     */
    protected function addAddressDebugHeader(string $header, array $addresses): static
    {
        $this->message->getHeaders()->addTextHeader(
            $header,
            implode(', ', array_map(fn ($a) => $a->toString(), $addresses)),
        );

        return $this;
    }
}
