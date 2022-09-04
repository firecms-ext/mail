# firecms-ext/mail

```shell
# 依赖安装
composer require firecms-ext/mail
# 发布配置
php bin/hyperf.php vendor:publish firecms-ext/mail
# 生成邮件
php bin/hyperf.php gen:mail MessageMail
```

# 调用方法

```php
FirecmsExt\Mail\Mail::to(mixed $users): PendingMail;
FirecmsExt\Mail\Mail::cc(mixed $users): PendingMail;
FirecmsExt\Mail\Mail::bcc(mixed $users): PendingMail;
FirecmsExt\Mail\Mail::later(MailableInterface $mailable, int $delay, ?string $queue = null): bool;
FirecmsExt\Mail\Mail::queue(MailableInterface $mailable, ?string $queue = null): bool;
FirecmsExt\Mail\Mail::send(MailableInterface $mailable):null|int;
```