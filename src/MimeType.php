<?php

declare(strict_types=1);

namespace Gems\Mime;

/**
 * Xac dinh MIME type an toan.
 *
 * Vi sao khong dung thang mime_content_type():
 *   - No doc magic bytes, khong doc duoi file, nen .js .css .csv deu ra
 *     "text/plain" -> sai header Content-Type khi serve file.
 *   - Tra ve false + E_WARNING khi loi.
 *   - File rong ra "application/x-empty", khong phai type that.
 *
 * @see https://www.php.net/manual/en/function.mime-content-type.php
 */
final class MimeType
{
    public const DEFAULT_TYPE = 'application/octet-stream';

    /** Gioi han doc buffer khi detect (du cho moi magic signature). */
    private const SNIFF_BYTES = 4096;

    private ?\finfo $finfo = null;

    private ?string $baseDir;

    /**
     * @param string|null $baseDir Neu dat, moi duong dan bat buoc phai nam trong
     *                             thu muc nay (chong path traversal). Rat nen dat
     *                             khi duong dan co the den tu request.
     */
    public function __construct(?string $baseDir = null)
    {
        if ($baseDir !== null) {
            $real = realpath($baseDir);
            if ($real === false) {
                throw MimeException::notReadable($baseDir);
            }
            $baseDir = $real;
        }

        $this->baseDir = $baseDir;
    }

    // ---------------------------------------------------------------- doc type

    /**
     * MIME type cua mot file. Luon tra ve string, khong bao gio false.
     *
     * @param bool $trustExtension true  = uu tien duoi file. Dung khi SERVE file
     *                                     do CHINH BAN tao ra.
     *                             false = chi doc magic bytes. BAT BUOC dung khi
     *                                     kiem tra file do NGUOI DUNG upload.
     */
    public function fromPath(string $path, bool $trustExtension = true): string
    {
        $real = $this->resolve($path);

        if ($trustExtension) {
            $ext = $this->extensionOf($real);
            if ($ext !== null && isset(MimeMap::EXTENSIONS[$ext])) {
                return MimeMap::EXTENSIONS[$ext];
            }
        }

        return $this->detect($real) ?? self::DEFAULT_TYPE;
    }

    /**
     * Doc MIME type thuc te tu magic bytes. Bo qua hoan toan duoi file.
     *
     * @return string|null null neu khong xac dinh duoc hoac file rong.
     */
    public function detect(string $path): ?string
    {
        $real = $this->resolve($path);
        $type = @$this->finfo()->file($real);

        if ($type === false || $type === '' || str_ends_with($type, '/x-empty')) {
            return null;
        }

        return $type;
    }

    /**
     * MIME type cua mot chuoi trong bo nho (blob tu DB, file vua tai ve...).
     */
    public function fromBuffer(string $buffer): string
    {
        if ($buffer === '') {
            return self::DEFAULT_TYPE;
        }

        $type = @$this->finfo()->buffer(substr($buffer, 0, self::SNIFF_BYTES));

        return ($type === false || $type === '') ? self::DEFAULT_TYPE : $type;
    }

    // ------------------------------------------------------------- kiem tra

    /**
     * File co dung mot trong cac dinh dang cho phep khong.
     * Chi doc noi dung that, bo qua duoi file va moi thu client gui len.
     *
     * @param list<string> $allowed vd: MimeMap::SAFE_IMAGES
     */
    public function isAllowed(string $path, array $allowed): bool
    {
        try {
            $type = $this->detect($path);
        } catch (MimeException) {
            return false;
        }

        return $type !== null && in_array($type, $allowed, true);
    }

    /**
     * MIME nay co an toan de tra ve inline cho trinh duyet khong.
     * SVG / HTML / XML tra ve false vi chung chay duoc <script>.
     */
    public function isSafeInline(string $mime): bool
    {
        return !in_array($this->normalize($mime), MimeMap::UNSAFE_INLINE, true);
    }

    /**
     * Duoi file chuan ung voi mot MIME type.
     * Dung de dat lai ten file upload, KHONG BAO GIO giu duoi do client gui.
     */
    public function toExtension(string $mime): ?string
    {
        $mime = $this->normalize($mime);

        if (isset(MimeMap::PREFERRED_EXTENSION[$mime])) {
            return MimeMap::PREFERRED_EXTENSION[$mime];
        }

        $ext = array_search($mime, MimeMap::EXTENSIONS, true);

        return $ext === false ? null : $ext;
    }

    // -------------------------------------------------------------- upload

    /**
     * Kiem tra day du mot file upload va sinh ten file an toan.
     *
     * Khac isAllowed() o cho ham nay con:
     *   - Xac minh day la file thuc su duoc upload (is_uploaded_file)
     *   - Chan duoi file nguy hiem, ke ca duoi kep "anh.php.jpg"
     *   - Voi anh: doi chieu them getimagesize() de loai polyglot
     *   - Sinh ten file ngau nhien, duoi lay tu MIME thuc te
     *
     * @param array{tmp_name?:string, name?:string, size?:int, error?:int} $file  Mot phan tu cua $_FILES
     * @param list<string> $allowed  Danh sach MIME cho phep
     * @param int $maxBytes          Kich thuoc toi da
     *
     * @return string Ten file an toan de luu (chua co thu muc)
     *
     * @throws MimeException Neu file khong hop le
     */
    public function validateUpload(array $file, array $allowed, int $maxBytes = 5_242_880): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new MimeException("Upload that bai, ma loi PHP: {$error}");
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new MimeException('tmp_name khong phai file upload hop le');
        }

        $size = filesize($tmp);
        if ($size === false || $size <= 0) {
            throw new MimeException('File rong');
        }
        if ($size > $maxBytes) {
            throw new MimeException("File qua lon: {$size} > {$maxBytes} bytes");
        }

        // Duoi file do client dat - chi dung de CHAN, khong dung de tin
        $clientName = (string) ($file['name'] ?? '');
        foreach ($this->allExtensionsOf($clientName) as $ext) {
            if (in_array($ext, MimeMap::DANGEROUS_EXTENSIONS, true)) {
                throw new MimeException("Duoi file bi chan: .{$ext}");
            }
        }

        // MIME that, doc tu magic bytes
        $type = @$this->finfo()->file($tmp);
        if ($type === false || !in_array($type, $allowed, true)) {
            throw new MimeException('Dinh dang file khong duoc phep: ' . var_export($type, true));
        }

        // Anh: xac minh them bang getimagesize() de loai file polyglot
        // (vd GIF89a hop le + code PHP dinh kem van qua duoc finfo)
        if (isset(MimeMap::IMAGE_TYPES[$type])) {
            // getimagesize() luon tra ve key 'mime' khi khong that bai,
            // nen khong can ?? null o day
            $info = @getimagesize($tmp);
            if ($info === false || $info['mime'] !== $type) {
                throw new MimeException('File anh hong hoac gia mao');
            }
            if ($info[0] < 1 || $info[1] < 1) {
                throw new MimeException('Kich thuoc anh khong hop le');
            }
        }

        $ext = $this->toExtension($type);
        if ($ext === null) {
            throw new MimeException("Khong xac dinh duoc duoi file cho: {$type}");
        }

        return bin2hex(random_bytes(16)) . '.' . $ext;
    }

    // -------------------------------------------------------------- serve

    /**
     * Sinh cac header can thiet de tra file ve trinh duyet.
     *
     * Luon kem X-Content-Type-Options: nosniff de trinh duyet khong tu doan
     * lai type va thuc thi nham.
     *
     * Voi type khong an toan inline (SVG, HTML...) tu dong ep tai xuong
     * thay vi hien thi - chan stored XSS.
     *
     * @param bool $forceDownload Ep tai xuong ke ca voi type an toan
     *
     * @return array<string, string> Map header => value
     */
    public function headersFor(
        string $path,
        ?string $downloadName = null,
        bool $forceDownload = false,
        string $charset = 'UTF-8'
    ): array {
        $real = $this->resolve($path);
        $type = $this->fromPath($real);

        $inline = !$forceDownload && $this->isSafeInline($type);

        if (!$inline && !$forceDownload) {
            // Type nguy hiem: ha xuong octet-stream de trinh duyet khong render
            $type = self::DEFAULT_TYPE;
        }

        $needsCharset = $inline && (
            str_starts_with($type, 'text/')
            || in_array($type, ['application/json', 'application/xml'], true)
        );

        $headers = [
            'Content-Type'           => $needsCharset ? "{$type}; charset={$charset}" : $type,
            'X-Content-Type-Options' => 'nosniff',
        ];

        $size = filesize($real);
        if ($size !== false) {
            $headers['Content-Length'] = (string) $size;
        }

        $name = $this->sanitizeFilename($downloadName ?? basename($real));
        $headers['Content-Disposition'] = $inline
            ? 'inline; filename="' . $name . '"'
            : 'attachment; filename="' . $name . '"';

        return $headers;
    }

    /**
     * Nhu headersFor() nhung gui luon bang header().
     */
    public function sendHeaders(
        string $path,
        ?string $downloadName = null,
        bool $forceDownload = false
    ): void {
        $headers = $this->headersFor($path, $downloadName, $forceDownload);

        if (headers_sent()) {
            return;
        }

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    // ------------------------------------------------------------- noi bo

    /**
     * Chuan hoa va kiem tra duong dan.
     *
     * Chan:
     *   - Stream wrapper (phar://, http://, data://) -> RCE / SSRF / deserialization
     *   - Null byte
     *   - Duong dan ra ngoai baseDir sau khi realpath() -> path traversal
     *
     * @throws MimeException
     */
    private function resolve(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw MimeException::notReadable($path);
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path) === 1) {
            throw MimeException::streamWrapper($path);
        }

        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw MimeException::notReadable($path);
        }

        if ($this->baseDir !== null && !str_starts_with($real, $this->baseDir . DIRECTORY_SEPARATOR)) {
            throw MimeException::outsideBase($real, $this->baseDir);
        }

        return $real;
    }

    private function finfo(): \finfo
    {
        if ($this->finfo === null) {
            try {
                $this->finfo = new \finfo(FILEINFO_MIME_TYPE);
            } catch (\Throwable) {
                throw MimeException::finfoFailed();
            }
        }

        return $this->finfo;
    }

    /** Duoi file cuoi cung, viet thuong. */
    private function extensionOf(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext === '' ? null : $ext;
    }

    /**
     * Moi thanh phan duoi file, de bat duoi kep kieu "anh.php.jpg".
     *
     * @return list<string>
     */
    private function allExtensionsOf(string $filename): array
    {
        $parts = explode('.', strtolower(basename(str_replace('\\', '/', $filename))));
        array_shift($parts); // bo phan ten, chi giu cac duoi

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    /** Bo ky tu co the pha header hoac gay path traversal trong ten tai ve. */
    private function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\w.\- ]+/u', '_', $name) ?? 'download';
        $name = trim($name, ' .');

        return $name === '' ? 'download' : mb_substr($name, 0, 200);
    }

    /** Bo phan parameter: "text/html; charset=utf-8" -> "text/html". */
    private function normalize(string $mime): string
    {
        return strtolower(trim(explode(';', $mime, 2)[0]));
    }
}
