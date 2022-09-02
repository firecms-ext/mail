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

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Clients\AccessKeyClient;
use Swift_Mime_SimpleMessage;

class AliyunDmTransport extends Transport
{
    /**
     * 阿里云 AccessKeyClient 实例。
     */
    protected AccessKeyClient $client;

    /**
     * 阿里云 DM 传输选项。
     */
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->client = AlibabaCloud::accessKeyClient($options['access_key_id'], $options['access_secret'])
            ->regionId($options['region_id'])
            ->asDefaultClient();
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->beforeSendPerformed($message);

        $query = [
            'RegionId' => $this->options['region_id'],
            'AddressType' => '1',
            'ClickTrace' => $this->options['click_trace'] ?? '0',
            'ReplyToAddress' => false,
            'AccountName' => $fromAddress = array_key_first($from = $message->getFrom()),
            'FromAlias' => $from[$fromAddress],
            'Subject' => $message->getSubject(),
            'ToAddress' => implode(',', array_keys($message->getTo())),
        ];

        foreach (array_merge([$message], $message->getChildren()) as $entity) {
            $contentType = $entity->getBodyContentType();
            if ($contentType === 'text/html') {
                $query['HtmlBody'] = $entity->getBody();
            } elseif ($contentType === 'text/plain') {
                $query['TextBody'] = $entity->getBody();
            }
        }

        $result = AlibabaCloud::rpc()
            ->product('Dm')
            ->scheme('https')
            ->version('2015-11-23')
            ->action('SingleSendMail')
            ->method('POST')
            ->host('dm.aliyuncs.com')
            ->options(compact('query'))
            ->request();

        $headers = $message->getHeaders();
        $headers->addTextHeader('X-Aliyun-DM-Env-ID', $result->get('EnvId'));
        $headers->addTextHeader('X-Aliyun-DM-Request-ID', $result->get('RequestId'));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}
