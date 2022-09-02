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
namespace FirecmsExt\Mail\Transport;

use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Swift_Mime_SimpleMessage;
use Swift_Mime_SimpleMimeEntity;

class LogTransport extends Transport
{
    /**
     * The Logger instance.
     */
    protected LoggerInterface $logger;

    /**
     * 创建一个新的日志传输实例。
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(ContainerInterface $container, array $options = [])
    {
        $this->logger = $container->get(LoggerFactory::class)->get(
            $options['name'] ?? 'mail.local',
            $options['group'] ?? 'default'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->beforeSendPerformed($message);

        $this->logger->debug($this->getMimeEntityString($message));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * 获取 LogTransport 实例的记录器。
     */
    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * 从 Swiftmailer 实体中获取可记录的字符串。
     */
    protected function getMimeEntityString(Swift_Mime_SimpleMimeEntity $entity): string
    {
        $string = (string) $entity->getHeaders() . PHP_EOL . $entity->getBody();

        foreach ($entity->getChildren() as $children) {
            $string .= PHP_EOL . PHP_EOL . $this->getMimeEntityString($children);
        }

        return $string;
    }
}
