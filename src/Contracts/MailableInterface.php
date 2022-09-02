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

use FirecmsExt\Contract\HasMailAddress;
use Hyperf\Utils\Collection;

interface MailableInterface
{
    /**
     * 设置邮件发送人。
     *
     * @return $this
     */
    public function from(HasMailAddress|string $address, ?string $name = null): static;

    /**
     * 设置邮件的“回复”地址。
     *
     * @return $this
     */
    public function replyTo(HasMailAddress|string $address, ?string $name = null): static;

    /**
     * 添加邮件的收件人。
     *
     * @return $this
     */
    public function cc(HasMailAddress|array|string|Collection $address, ?string $name = null): static;

    /**
     * 确定是否在可邮寄邮件上设置了给定的收件人。
     */
    public function hasCc(HasMailAddress|string $address, ?string $name = null): bool;

    /**
     * 添加邮件的收件人。
     *
     * @return $this
     */
    public function bcc(HasMailAddress|array|string|Collection $address, ?string $name = null): static;

    /**
     * 确定是否在可邮寄邮件上设置了给定的收件人。
     */
    public function hasBcc(HasMailAddress|string $address, ?string $name = null): bool;

    /**
     * 添加邮件的收件人。
     *
     * @return $this
     */
    public function to(HasMailAddress|array|string|Collection $address, ?string $name = null): static;

    /**
     * 确定是否在可邮寄邮件上设置了给定的收件人。
     */
    public function hasTo(HasMailAddress|string $address, ?string $name = null): bool;

    /**
     * 设置消息的主题。
     *
     * @return $this
     */
    public function subject(string $subject): static;

    /**
     * 设置此消息的优先级。
     *
     * 整数形式，1 优先级最高，5 优先级最低。
     *
     * @return $this
     */
    public function priority(int $level): static;

    /**
     * 设置消息的区域设置。
     *
     * @return $this
     */
    public function locale(string $locale): static;

    /**
     * 设置应用于发送消息的邮件服务器的名称。
     *
     * @return $this
     */
    public function mailer(string $mailer): static;

    /**
     * 为消息设置html视图模板。
     *
     * @return $this
     */
    public function htmlView(string $template): static;

    /**
     * 为消息设置纯文本视图模板。
     *
     * @return $this
     */
    public function textView(string $template): static;

    /**
     * 设置消息的视图数据。
     *
     * @return $this
     */
    public function with(array|string $key, mixed $value = null): static;

    /**
     * 为消息设置呈现的HTML内容。
     *
     * @return $this
     */
    public function htmlBody(string $content): static;

    /**
     * 为消息设置呈现的纯文本内容。
     *
     * @return $this
     */
    public function textBody(string $content): static;

    /**
     * 在消息上附加一个文件。
     *
     * @return $this
     */
    public function attach(string $file, array $options = []): static;

    /**
     * 将文件从存储器附加到消息。
     *
     * @return $this
     */
    public function attachFromStorage(?string $adapter, string $path, ?string $name = null, array $options = []): static;

    /**
     * 作为附件附加内存中的数据。
     *
     * @return $this
     */
    public function attachData(string $data, string $name, array $options = []): static;

    /**
     * 将消息呈现为视图。
     */
    public function render(MailerInterface|MailManagerInterface $mailer = null): string;

    /**
     * 使用给定的邮件器发送消息，并返回失败的收件人。
     */
    public function send(MailerInterface|MailManagerInterface $mailer = null): array;

    /**
     * 对要发送的消息进行排队。
     */
    public function queue(?string $queue = null): bool;

    /**
     * 在给定的延迟之后交付排队的消息。
     */
    public function later(int $delay, ?string $queue = null): bool;
}
