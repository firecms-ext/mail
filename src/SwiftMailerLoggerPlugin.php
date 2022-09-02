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

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Swift_Events_CommandEvent;
use Swift_Events_CommandListener;
use Swift_Events_ResponseEvent;
use Swift_Events_ResponseListener;
use Swift_Events_SendEvent;
use Swift_Events_SendListener;
use Swift_Events_TransportChangeEvent;
use Swift_Events_TransportChangeListener;
use Swift_Events_TransportExceptionEvent;
use Swift_Events_TransportExceptionListener;
use Swift_TransportException;

class SwiftMailerLoggerPlugin implements Swift_Events_SendListener, Swift_Events_CommandListener, Swift_Events_ResponseListener, Swift_Events_TransportChangeListener, Swift_Events_TransportExceptionListener
{
    /**
     * The PSR-3 logger.
     */
    protected LoggerInterface $logger;

    /**
     * Map of events to log-levels.
     */
    protected array $levels = [
        'sendPerformed.SUCCESS' => LogLevel::INFO,
        'sendPerformed.TENTATIVE' => LogLevel::WARNING,
        'sendPerformed.NOT_SUCCESS' => LogLevel::ERROR,
        'sendPerformed.PENDING' => LogLevel::DEBUG,
        'sendPerformed.SPOOLED' => LogLevel::DEBUG,
        'exceptionThrown' => LogLevel::ERROR,
        'beforeSendPerformed' => LogLevel::DEBUG,
        'commandSent' => LogLevel::DEBUG,
        'responseReceived' => LogLevel::DEBUG,
        'beforeTransportStarted' => LogLevel::DEBUG,
        'transportStarted' => LogLevel::DEBUG,
        'beforeTransportStopped' => LogLevel::DEBUG,
        'transportStopped' => LogLevel::DEBUG,
    ];

    public function __construct(LoggerInterface $logger, array $levels = [])
    {
        $this->logger = $logger;
        foreach ($levels as $evt => $level) {
            $this->levels[$evt] = $level;
        }
    }

    /**
     * Invoked immediately before the Message is sent.
     */
    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
        $level = $this->levels['beforeSendPerformed'];
        $this->log($level, 'MESSAGE (beforeSend): ', [
            'message' => $evt->getMessage()->toString(),
        ]);
    }

    /**
     * Invoked immediately after the Message is sent.
     */
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $result = $evt->getResult();
        $failed_recipients = $evt->getFailedRecipients();
        $message = $evt->getMessage();

        $level = match ($result) {
            Swift_Events_SendEvent::RESULT_PENDING => $this->levels['sendPerformed.PENDING'],
            Swift_Events_SendEvent::RESULT_SPOOLED => $this->levels['sendPerformed.SPOOLED'],
            Swift_Events_SendEvent::RESULT_TENTATIVE => $this->levels['sendPerformed.TENTATIVE'],
            Swift_Events_SendEvent::RESULT_SUCCESS => $this->levels['sendPerformed.SUCCESS'],
            default => $this->levels['sendPerformed.NOT_SUCCESS'],
        };

        $this->log($level, 'MESSAGE (sendPerformed): ', [
            'result' => $result,
            'failed_recipients' => $failed_recipients,
            'message' => $message->toString(),
        ]);
    }

    /**
     * Invoked immediately following a command being sent.
     */
    public function commandSent(Swift_Events_CommandEvent $evt)
    {
        $level = $this->levels['commandSent'];
        $command = $evt->getCommand();
        $this->log($level, sprintf('>> %s', $command));
    }

    /**
     * Invoked immediately following a response coming back.
     */
    public function responseReceived(Swift_Events_ResponseEvent $evt)
    {
        $level = $this->levels['responseReceived'];
        $response = $evt->getResponse();
        $this->log($level, sprintf('<< %s', $response));
    }

    /**
     * Invoked just before a Transport is started.
     */
    public function beforeTransportStarted(Swift_Events_TransportChangeEvent $evt)
    {
        $level = $this->levels['beforeTransportStarted'];
        $transportName = get_class($evt->getSource());
        $this->log($level, sprintf('++ Starting %s', $transportName));
    }

    /**
     * Invoked immediately after the Transport is started.
     */
    public function transportStarted(Swift_Events_TransportChangeEvent $evt)
    {
        $level = $this->levels['transportStarted'];
        $transportName = get_class($evt->getSource());
        $this->log($level, sprintf('++ %s started', $transportName));
    }

    /**
     * Invoked just before a Transport is stopped.
     */
    public function beforeTransportStopped(Swift_Events_TransportChangeEvent $evt)
    {
        $level = $this->levels['beforeTransportStopped'];
        $transportName = get_class($evt->getSource());
        $this->log($level, sprintf('++ Stopping %s', $transportName));
    }

    /**
     * Invoked immediately after the Transport is stopped.
     */
    public function transportStopped(Swift_Events_TransportChangeEvent $evt)
    {
        $level = $this->levels['transportStopped'];
        $transportName = get_class($evt->getSource());
        $this->log($level, sprintf('++ %s stopped', $transportName));
    }

    /**
     * Invoked as a TransportException is thrown in the Transport system.
     *
     * @throws Swift_TransportException
     */
    public function exceptionThrown(Swift_Events_TransportExceptionEvent $evt)
    {
        $e = $evt->getException();
        $message = $e->getMessage();

        $level = $this->levels['exceptionThrown'];
        $this->log($level, sprintf('!! %s', $message));

        $evt->cancelBubble();
        throw new Swift_TransportException($message);
    }

    /**
     * Adds the message and invokes the logger->log() method.
     */
    protected function log(mixed $level, string $message, array $context = [])
    {
        // Using a falsy level disables logging
        if ($level) {
            $this->logger->log($level, $message, $context);
        }
    }
}
