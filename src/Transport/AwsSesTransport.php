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

use Aws\Ses\SesClient;
use Swift_Mime_SimpleMessage;

class AwsSesTransport extends Transport
{
    /**
     * The Amazon SES instance.
     */
    protected SesClient $ses;

    /**
     * The Amazon SES transmission options.
     */
    protected array $options = [];

    /**
     * Create a new SES transport instance.
     */
    public function __construct(SesClient $ses, array $options = [])
    {
        $this->ses = $ses;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->beforeSendPerformed($message);

        $result = $this->ses->sendRawEmail(
            array_merge(
                $this->options,
                [
                    'Source' => key($message->getSender() ?: $message->getFrom()),
                    'RawMessage' => [
                        'Data' => $message->toString(),
                    ],
                ]
            )
        );

        $message->getHeaders()->addTextHeader('X-AWS-SES-Message-ID', $result->get('MessageId'));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}
