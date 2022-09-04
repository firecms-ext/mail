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

use Closure;
use FirecmsExt\Mail\Contracts\MailableInterface;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Macroable\Macroable;

class Attachment
{
    use Macroable;

    /**
     * The attached file's filename.
     */
    public ?string $as;

    /**
     * The attached file's mime type.
     */
    public ?string $mime;

    /**
     * A callback that attaches the attachment to the mail message.
     */
    protected Closure $resolver;

    /**
     * Create a mail attachment.
     */
    private function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Create a mail attachment from a path.
     */
    public static function fromPath(string $path): static
    {
        return new static(fn ($attachment, $pathStrategy) => $pathStrategy($path, $attachment));
    }

    /**
     * Create a mail attachment from in-memory data.
     */
    public static function fromData(Closure $data, string $name): static
    {
        return (new static(
            fn ($attachment, $pathStrategy, $dataStrategy) => $dataStrategy($data, $attachment)
        ))->as($name);
    }

    /**
     * Create a mail attachment from a file in the default storage disk.
     */
    public static function fromStorage(string $path): static
    {
        return static::fromStorageDisk(null, $path);
    }

    /**
     * Create a mail attachment from a file in the specified storage disk.
     */
    public static function fromStorageDisk(?string $disk, string $path): static
    {
        return new static(function ($attachment, $pathStrategy, $dataStrategy) use ($disk, $path) {
            $storage = make(
                FilesystemFactory::class
            )->disk($disk);

            $attachment
                ->as($attachment->as ?? basename($path))
                ->withMime($attachment->mime ?? $storage->mimeType($path));

            return $dataStrategy(fn () => $storage->get($path), $attachment);
        });
    }

    /**
     * Set the attached file's filename.
     *
     * @return $this
     */
    public function as(string $name): static
    {
        $this->as = $name;

        return $this;
    }

    /**
     * Set the attached file's mime type.
     *
     * @return $this
     */
    public function withMime(string $mime): static
    {
        $this->mime = $mime;

        return $this;
    }

    /**
     * Attach the attachment with the given strategies.
     */
    public function attachWith(Closure $pathStrategy, Closure $dataStrategy): mixed
    {
        return ($this->resolver)($this, $pathStrategy, $dataStrategy);
    }

    /**
     * Attach the attachment to a built-in mail type.
     */
    public function attachTo(Message|MailableInterface $mail): mixed
    {
        return $this->attachWith(
            fn ($path) => $mail->attach($path, ['as' => $this->as, 'mime' => $this->mime]),
            fn ($data) => $mail->attachData($data(), $this->as, ['mime' => $this->mime])
        );
    }
}
