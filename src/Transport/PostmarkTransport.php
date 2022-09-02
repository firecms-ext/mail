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

use Postmark\ThrowExceptionOnFailurePlugin;
use Postmark\Transport as ThePostmarkTransport;
use Swift_Events_EventListener;
use Swift_Mime_SimpleMessage;

class PostmarkTransport extends Transport
{
    protected \Postmark\Transport $transport;

    public function __construct(array $options)
    {
        $this->transport = new ThePostmarkTransport($options['token']);
        $this->transport->registerPlugin(new ThrowExceptionOnFailurePlugin());
    }

    public function isStarted()
    {
        return call_user_func_array([$this->transport, __FUNCTION__], func_get_args());
    }

    public function start()
    {
        return call_user_func_array([$this->transport, __FUNCTION__], func_get_args());
    }

    public function stop()
    {
        return call_user_func_array([$this->transport, __FUNCTION__], func_get_args());
    }

    public function ping()
    {
        return call_user_func_array([$this->transport, __FUNCTION__], func_get_args());
    }

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        return call_user_func_array([$this->transport, __FUNCTION__], func_get_args());
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->beforeSendPerformed($message);

        $count = $this->transport->send($message, $failedRecipients);

        $this->sendPerformed($message);

        return $count;
    }
}
