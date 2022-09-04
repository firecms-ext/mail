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

use Hyperf\Utils\Traits\ForwardsCalls;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;

class SentMessage
{
    use ForwardsCalls;

    /**
     * The Symfony SentMessage instance.
     */
    protected SymfonySentMessage $sentMessage;

    /**
     * Create a new SentMessage instance.
     */
    public function __construct(SymfonySentMessage $sentMessage)
    {
        $this->sentMessage = $sentMessage;
    }

    /**
     * Dynamically pass missing methods to the Symfony instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->sentMessage, $method, $parameters);
    }

    /**
     * Get the underlying Symfony Email instance.
     */
    public function getSymfonySentMessage(): SymfonySentMessage
    {
        return $this->sentMessage;
    }
}
