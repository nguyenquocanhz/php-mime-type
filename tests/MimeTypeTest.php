<?php

declare(strict_types=1);

namespace Gems\Mime\Tests;

use Gems\Mime\MimeException;
use Gems\Mime\MimeMap;
use Gems\Mime\MimeType;
use PHPUnit\Framework\TestCase;

final class MimeTypeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mime-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function write(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    /** PNG 1x1 hop le, dung lam mau anh that. */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        ) ?: '';
    }

    // ------------------------------------------------- duoi file vs magic bytes

    public function testExtensionWinsForTextBasedWebAssets(): void
    {
        $mime = new MimeType();

        // mime_content_type() tra "text/plain" cho ca hai - sai header khi serve
        self::assertSame('text/css', $mime->fromPath($this->write('a.css', 'body{color:red}')));
        self::assertSame('text/javascript', $mime->fromPath($this->write('a.js', 'const x=1;')));
    }

    public function testDetectIgnoresExtension(): void
    {
        $mime = new MimeType();
        $path = $this->write('anh.jpg', 'day khong phai anh, chi la text thuong');

        self::assertSame('image/jpeg', $mime->fromPath($path, trustExtension: true));
        self::assertSame('text/plain', $mime->detect($path));
    }

    public function testRealPngDetected(): void
    {
        $mime = new MimeType();

        self::assertSame('image/png', $mime->detect($this->write('x.png', $this->pngBytes())));
    }

    public function testEmptyFileReturnsDefault(): void
    {
        $mime = new MimeType();
        $path = $this->write('rong.bin', '');

        self::assertNull($mime->detect($path));
        self::assertSame(MimeType::DEFAULT_TYPE, $mime->fromPath($path));
    }

    // ---------------------------------------------------------------- buffer

    public function testFromBuffer(): void
    {
        $mime = new MimeType();

        self::assertSame('image/png', $mime->fromBuffer($this->pngBytes()));
        self::assertSame(MimeType::DEFAULT_TYPE, $mime->fromBuffer(''));
    }

    // ------------------------------------------------------------- bao mat

    public function testStreamWrapperIsRejected(): void
    {
        $mime = new MimeType();

        $this->expectException(MimeException::class);
        $mime->fromPath('phar://payload.phar/x.txt');
    }

    public function testPathOutsideBaseDirIsRejected(): void
    {
        $this->write('trong.txt', 'ok');
        $sub = $this->dir . '/sub';
        mkdir($sub);

        $mime = new MimeType($sub);

        $this->expectException(MimeException::class);
        $mime->fromPath($sub . '/../trong.txt');
    }

    public function testPathInsideBaseDirIsAccepted(): void
    {
        $mime = new MimeType($this->dir);

        self::assertSame('text/plain', $mime->fromPath($this->write('ok.txt', 'xin chao')));
    }

    public function testMissingFileThrowsInsteadOfWarning(): void
    {
        $mime = new MimeType();

        $this->expectException(MimeException::class);
        $mime->fromPath($this->dir . '/khong-ton-tai.jpg');
    }

    public function testSvgIsNotSafeInline(): void
    {
        $mime = new MimeType();

        self::assertFalse($mime->isSafeInline('image/svg+xml'));
        self::assertFalse($mime->isSafeInline('text/html; charset=utf-8'));
        self::assertTrue($mime->isSafeInline('image/png'));
    }

    public function testSvgIsServedAsAttachmentNotInline(): void
    {
        $mime = new MimeType();
        $path = $this->write('xss.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $headers = $mime->headersFor($path);

        self::assertSame(MimeType::DEFAULT_TYPE, $headers['Content-Type']);
        self::assertStringStartsWith('attachment;', $headers['Content-Disposition']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testSafeImageIsServedInline(): void
    {
        $mime = new MimeType();
        $headers = $mime->headersFor($this->write('x.png', $this->pngBytes()));

        self::assertSame('image/png', $headers['Content-Type']);
        self::assertStringStartsWith('inline;', $headers['Content-Disposition']);
    }

    public function testDownloadFilenameCannotInjectHeader(): void
    {
        $mime = new MimeType();
        $headers = $mime->headersFor(
            $this->write('x.png', $this->pngBytes()),
            downloadName: "evil\r\nSet-Cookie: a=b\".png"
        );
        $disposition = $headers['Content-Disposition'];

        // Khong con CR/LF -> khong the xuong dong tao header moi
        self::assertStringNotContainsString("\r", $disposition);
        self::assertStringNotContainsString("\n", $disposition);

        // Chi con dung 2 dau nhay bao ngoai -> khong thoat khoi filename="..."
        self::assertSame(2, substr_count($disposition, '"'));

        // Chuoi "Set-Cookie" con lai duoi dang text trong ten file la vo hai,
        // nen khong assert vang mat no.
        self::assertSame('inline; filename="evil_Set-Cookie_ a_b_.png"', $disposition);
    }

    public function testInvalidUtf8FilenameFallsBackSafely(): void
    {
        $mime = new MimeType();
        $headers = $mime->headersFor(
            $this->write('x.png', $this->pngBytes()),
            downloadName: "\xC3\x28bad.png"
        );

        self::assertSame('inline; filename="download"', $headers['Content-Disposition']);
    }

    // ------------------------------------------------------------ extension

    public function testToExtensionPrefersCanonical(): void
    {
        $mime = new MimeType();

        self::assertSame('jpg', $mime->toExtension('image/jpeg'));
        self::assertSame('js', $mime->toExtension('text/javascript'));
        self::assertSame('json', $mime->toExtension('application/json; charset=UTF-8'));
        self::assertNull($mime->toExtension('application/khong-ton-tai'));
    }

    public function testDangerousExtensionListCoversDoubleExtensionTricks(): void
    {
        foreach (['php', 'phtml', 'phar', 'htaccess', 'svg', 'jsp', 'exe'] as $ext) {
            self::assertContains($ext, MimeMap::DANGEROUS_EXTENSIONS, "thieu .{$ext}");
        }
    }
}
