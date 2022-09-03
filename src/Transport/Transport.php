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

use Swift_Events_EventListener;
use Swift_Events_SendEvent;
use Swift_Mime_SimpleMessage;
use Swift_Transport;

abstract class Transport implements Swift_Transport
{
    /**
     * 向传输程序注册的插件。
     */
    public array $plugins = [];

    /**
     * {@inheritdoc}
     */
    public function isStarted(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): bool
    {
        return true;
    }

    /**
     * 向传输注册一个插件。
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->plugins[] = $plugin;
    }

    /**
     * 迭代已注册的插件并执行插件的方法。
     */
    protected function beforeSendPerformed(Swift_Mime_SimpleMessage $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'beforeSendPerformed')) {
                $plugin->beforeSendPerformed($event);
            }
        }
    }

    /**
     * 迭代已注册的插件并执行插件的方法。
     */
    protected function sendPerformed(Swift_Mime_SimpleMessage $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'sendPerformed')) {
                $plugin->sendPerformed($event);
            }
        }
    }

    /**
     * 获取接收者的数量。
     */
    protected function numberOfRecipients(Swift_Mime_SimpleMessage $message): int
    {
        return count(array_merge(
            (array) $message->getTo(),
            (array) $message->getCc(),
            (array) $message->getBcc()
        ));
    }
}
