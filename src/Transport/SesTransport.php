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

use Aws\Exception\AwsException;
use Aws\Ses\SesClient;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;

class SesTransport extends AbstractTransport
{
    /**
     * Amazon SES实例。
     */
    protected SesClient $ses;

    /**
     * 亚马逊 SES 传输选项。
     */
    protected array $options = [];

    /**
     * 创建一个新的 SES 传输实例。
     */
    public function __construct(SesClient $ses, array $options = [])
    {
        $this->ses = $ses;
        $this->options = $options;

        parent::__construct();
    }

    /**
     * 获取传输的字符串表示形式。
     */
    public function __toString(): string
    {
        return 'ses';
    }

    /**
     * 获取SesTransport实例的Amazon SES客户端。
     */
    public function ses(): SesClient
    {
        return $this->ses;
    }

    /**
     * 获取传输正在使用的传输选项。
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 设置传输正在使用的传输选项。
     */
    public function setOptions(array $options): array
    {
        return $this->options = $options;
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    protected function doSend(SentMessage $message): void
    {
        $options = $this->options;

        if ($message->getOriginalMessage() instanceof Message) {
            foreach ($message->getOriginalMessage()->getHeaders()->all() as $header) {
                if ($header instanceof MetadataHeader) {
                    $options['Tags'][] = ['Name' => $header->getKey(), 'Value' => $header->getValue()];
                }
            }
        }

        try {
            $result = $this->ses->sendRawEmail(
                array_merge(
                    $options,
                    [
                        'Source' => $message->getEnvelope()->getSender()->toString(),
                        'Destinations' => collect($message->getEnvelope()->getRecipients())
                            ->map
                            ->toString()
                            ->values()
                            ->all(),
                        'RawMessage' => [
                            'Data' => $message->toString(),
                        ],
                    ]
                )
            );
        } catch (AwsException $e) {
            $reason = $e->getAwsErrorMessage() ?? $e->getMessage();

            throw new \Exception(
                sprintf('Request to AWS SES API failed. Reason: %s.', $reason),
                is_int($e->getCode()) ? $e->getCode() : 0,
                $e
            );
        }

        $messageId = $result->get('MessageId');

        $message->getOriginalMessage()->getHeaders()->addHeader('X-Message-ID', $messageId);
        $message->getOriginalMessage()->getHeaders()->addHeader('X-SES-Message-ID', $messageId);
    }
}
