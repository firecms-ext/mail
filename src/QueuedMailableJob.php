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

use FirecmsExt\Mail\Contracts\MailableInterface;
use FirecmsExt\Mail\Contracts\MailManagerInterface;
use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\ApplicationContext;

class QueuedMailableJob extends Job
{
    public MailableInterface $mailable;

    public function __construct(MailableInterface $mailable)
    {
        $this->mailable = $mailable;
    }

    public function handle()
    {
        $this->mailable->send(ApplicationContext::getContainer()->get(MailManagerInterface::class));
    }
}
