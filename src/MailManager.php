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
use FirecmsExt\Mail\Contracts\MailManagerInterface;
use FirecmsExt\Mail\Transport\ArrayTransport;
use FirecmsExt\Mail\Transport\LogTransport;
use FirecmsExt\Mail\Transport\SesTransport;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailManager implements MailManagerInterface
{
    /**
     * 初始化上下文.
     */
    protected ContainerInterface $container;

    /**
     * 初始化配置.
     */
    protected ConfigInterface $config;

    /**
     * 已解析邮件的数组。
     */
    protected array $mailers = [];

    /**
     * 已注册的自定义驱动程序创建者。
     */
    protected array $customCreators = [];

    /**
     * 创建一个新的邮件管理器实例。
     */
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * 动态调用默认驱动程序实例。
     * @param mixed $method
     * @param mixed $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->mailer()->{$method}(...$parameters);
    }

    /**
     * 按名称获取邮件服务器实例。
     */
    public function mailer(?string $name = null): Mailer
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->mailers[$name] = $this->get($name);
    }

    /**
     * 获取邮件驱动程序实例。
     */
    public function driver(?string $driver = null): Mailer
    {
        return $this->mailer($driver);
    }

    /**
     * 获取默认邮件驱动程序名称。
     */
    public function getDefaultDriver(): string
    {
        // 这里我们将检查"driver"键是否存在，如果存在我们将使用
        // 作为默认驱动程序，以提供对旧样式的支持
        // Laravel邮件配置文件的向后兼容性。
        return $this->config->get('mail.driver', $this->config->get('mail.default'));
    }

    /**
     * 创建一个新的传输实例。
     * @throws InvalidArgumentException
     */
    public function createSymfonyTransport(array $config): TransportInterface
    {
        // Here we will check if the "transport" key exists and if it doesn't we will
        // assume an application is still using the legacy mail configuration file
        // format and use the "mail.driver" configuration option instead for BC.
        $transport = $config['transport'] ?? $this->config->get('mail.driver');

        if (isset($this->customCreators[$transport])) {
            return call_user_func($this->customCreators[$transport], $config);
        }

        if (trim($transport ?? '') === '' || ! method_exists($this, $method = 'create' . ucfirst($transport) . 'Transport')) {
            throw new InvalidArgumentException("Unsupported mail transport [{$transport}].");
        }

        return $this->{$method}($config);
    }

    /**
     * 设置默认邮件驱动程序名称。
     */
    public function setDefaultDriver(string $name): void
    {
        if ($this->config->get('mail.driver')) {
            $this->config->set('mail.driver', $name);
        }

        $this->config->set('mail.default', $name);
    }

    /**
     * 断开给定邮件服务器并从本地缓存中删除。
     */
    public function purge(string $name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        unset($this->mailers[$name]);
    }

    /**
     * 注册一个自定义传输创建器闭包。
     *
     * @return $this
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * 忘记所有已解析的邮件服务器实例。
     *
     * @return $this
     */
    public function forgetMailers(): static
    {
        $this->mailers = [];

        return $this;
    }

    /**
     * 尝试从本地缓存中获取邮件。
     */
    protected function get(string $name): Mailer
    {
        return $this->mailers[$name] ?? $this->resolve($name);
    }

    /**
     * 解析给定的邮件。
     */
    protected function resolve(string $name): Mailer
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("MailerInterface [{$name}] is not defined.");
        }

        // 一旦我们创建了邮件实例，我们将设置一个容器实例
        // 发送邮件。这允许我们通过容器解析 mailer 类
        // 最大可测试性的类，而不是传递闭包。
        $mailer = new Mailer(
            $name,
            $this->createSymfonyTransport($config),
            $this->container->get(EventDispatcherInterface::class)
        );

        // 接下来我们将设置这个邮件上的所有全局地址，这允许
        // 方便统一所有"from"地址以及方便调试
        // 发送的消息，因为这些将发送到一个单一的电子邮件地址。
        foreach (['from', 'reply_to', 'to', 'return_path'] as $type) {
            $this->setGlobalAddress($mailer, $config, $type);
        }

        return $mailer;
    }

    /**
     * 获取邮件连接配置。
     */
    protected function getConfig(string $name): array
    {
        return $this->config->get('mail.driver')
            ? $this->config->get('mail')
            : $this->config->get("mail.mailers.{$name}");
    }

    /**
     * 创建一个 Symfony SMTP 传输驱动程序实例。
     */
    protected function createSmtpTransport(array $config): EsmtpTransport
    {
        $factory = new EsmtpTransportFactory();

        $transport = $factory->create(new Dsn(
            ! empty($config['encryption']) && $config['encryption'] === 'tls'
                ? (($config['port'] == 465) ? 'smtps' : 'smtp') : '',
            $config['host'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['port'] ?? null,
            $config
        ));

        return $this->configureSmtpTransport($transport, $config);
    }

    /**
     * 配置额外的SMTP驱动程序选项。
     */
    protected function configureSmtpTransport(EsmtpTransport $transport, array $config): EsmtpTransport
    {
        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            if (isset($config['source_ip'])) {
                $stream->setSourceIp($config['source_ip']);
            }

            if (isset($config['timeout'])) {
                $stream->setTimeout($config['timeout']);
            }
        }

        return $transport;
    }

    /**
     * 创建 Symfony Sendmail Transport 驱动程序的实例。
     */
    protected function createSendmailTransport(array $config): SendmailTransport
    {
        return new SendmailTransport(
            $config['path'] ?? $this->config->get('mail.sendmail')
        );
    }

    /**
     * 创建一个 Symfony Amazon SES Transport 驱动程序实例。
     */
    protected function createSesTransport(array $config): SesTransport
    {
        $config = array_merge(
            $this->config->get('services.ses', []),
            ['version' => 'latest', 'service' => 'email'],
            $config
        );

        $config = Arr::except($config, ['transport']);

        return new SesTransport(
            new SesClient($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * 将SES凭据添加到配置数组。
     */
    protected function addSesCredentials(array $config): array
    {
        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * 创建Symfony邮件传输驱动程序的实例。
     */
    protected function createMailTransport(): SendmailTransport
    {
        return new SendmailTransport();
    }

    /**
     * 创建一个Symfony Mailgun传输驱动程序实例。
     *
     * @return \Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport
     */
    protected function createMailgunTransport(array $config)
    {
        $factory = new MailgunTransportFactory();

        if (! isset($config['secret'])) {
            $config = $this->config->get->get('services.mailgun', []);
        }

        return $factory->create(new Dsn(
            'mailgun+' . ($config['scheme'] ?? 'https'),
            $config['endpoint'] ?? 'default',
            $config['secret'],
            $config['domain']
        ));
    }

    /**
     * Create an instance of the Symfony Postmark Transport driver.
     *
     * @return \Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport
     */
    protected function createPostmarkTransport(array $config)
    {
        $factory = new PostmarkTransportFactory();

        $options = isset($config['message_stream_id'])
            ? ['message_stream' => $config['message_stream_id']]
            : [];

        return $factory->create(new Dsn(
            'postmark+api',
            'default',
            $config['token'] ?? $this->config->get->get('services.postmark.token'),
            null,
            null,
            $options
        ));
    }

    /**
     * 创建一个Symfony故障转移传输驱动程序实例。
     */
    protected function createFailoverTransport(array $config): FailoverTransport
    {
        $transports = [];

        foreach ($config['mailers'] as $name) {
            $config = $this->getConfig($name);

            if (is_null($config)) {
                throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
            }

            // Now, we will check if the "driver" key exists and if it does we will set
            // the transport configuration parameter in order to offer compatibility
            // with any Laravel <= 6.x application style mail configuration files.
            $transports[] = $this->config->get('mail.driver')
                ? $this->createSymfonyTransport(array_merge($config, ['transport' => $name]))
                : $this->createSymfonyTransport($config);
        }

        return new FailoverTransport($transports);
    }

    /**
     * 创建日志传输驱动程序实例。
     */
    protected function createLogTransport(array $config): LogTransport
    {
        $logger = $this->container->get(LoggerInterface::class);

        if ($logger instanceof LoggerInterface) {
            $logger = $logger->channel(
                $config['channel'] ?? $this->config->get('mail.log_channel')
            );
        }

        return new LogTransport($logger);
    }

    /**
     * 创建阵列传输驱动程序的实例。
     */
    protected function createArrayTransport(): ArrayTransport
    {
        return new ArrayTransport();
    }

    /**
     * 按类型在邮件服务器上设置全局地址。
     */
    protected function setGlobalAddress(Mailer $mailer, array $config, string $type): void
    {
        $address = Arr::get($config, $type, $this->config->get('mail.' . $type));

        if (is_array($address) && isset($address['address'])) {
            $mailer->{'always' . Str::studly($type)}($address['address'], $address['name']);
        }
    }
}
