<?php

declare(strict_types=1);

namespace Gems\Mime;

use RuntimeException;

/**
 * Loi khi xu ly MIME / duong dan file.
 *
 * Thong diep cua exception nay CHI dung cho log noi bo - khong echo ra cho
 * nguoi dung vi no chua duong dan tuyet doi tren server (A05 - lo thong tin).
 */
final class MimeException extends RuntimeException
{
    public const E_STREAM_WRAPPER = 1;
    public const E_OUTSIDE_BASE   = 2;
    public const E_NOT_READABLE   = 3;
    public const E_FINFO_FAILED   = 4;

    public static function streamWrapper(string $path): self
    {
        return new self("Duong dan chua stream wrapper, bi tu choi: {$path}", self::E_STREAM_WRAPPER);
    }

    public static function outsideBase(string $path, string $baseDir): self
    {
        return new self("Duong dan nam ngoai thu muc cho phep ({$baseDir}): {$path}", self::E_OUTSIDE_BASE);
    }

    public static function notReadable(string $path): self
    {
        return new self("File khong ton tai hoac khong doc duoc: {$path}", self::E_NOT_READABLE);
    }

    public static function finfoFailed(): self
    {
        return new self('Khong khoi tao duoc fileinfo - kiem tra ext-fileinfo', self::E_FINFO_FAILED);
    }
}
