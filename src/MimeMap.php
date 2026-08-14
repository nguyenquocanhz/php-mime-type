<?php

declare(strict_types=1);

namespace Gems\Mime;

/**
 * Bang tra MIME <-> phan mo rong, kem phan loai muc do an toan.
 */
final class MimeMap
{
    /**
     * Duoi file -> MIME type. Dung khi SERVE file.
     *
     * @var array<string, string>
     */
    public const EXTENSIONS = [
        // Web
        'html' => 'text/html',
        'htm'  => 'text/html',
        'xhtml' => 'application/xhtml+xml',
        'css'  => 'text/css',
        'js'   => 'text/javascript',
        'mjs'  => 'text/javascript',
        'json' => 'application/json',
        'map'  => 'application/json',
        'xml'  => 'application/xml',
        'rss'  => 'application/rss+xml',
        'wasm' => 'application/wasm',
        'php'  => 'text/x-php',

        // Van ban
        'txt' => 'text/plain',
        'md'  => 'text/markdown',
        'csv' => 'text/csv',
        'log' => 'text/plain',
        'ics' => 'text/calendar',

        // Anh
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg'  => 'image/svg+xml',
        'bmp'  => 'image/bmp',
        'tif'  => 'image/tiff',
        'tiff' => 'image/tiff',
        'ico'  => 'image/vnd.microsoft.icon',
        'heic' => 'image/heic',

        // Font
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',

        // Audio / Video
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'oga'  => 'audio/ogg',
        'm4a'  => 'audio/mp4',
        'flac' => 'audio/flac',
        'aac'  => 'audio/aac',
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogv'  => 'video/ogg',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',

        // Nen
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        '7z'  => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz'  => 'application/gzip',
        'bz2' => 'application/x-bzip2',

        // Tai lieu
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',

        // Khac
        'apk' => 'application/vnd.android.package-archive',
        'exe' => 'application/vnd.microsoft.portable-executable',
        'sql' => 'application/sql',
    ];

    /**
     * MIME -> duoi file uu tien, cho cac truong hop mot MIME co nhieu duoi.
     *
     * @var array<string, string>
     */
    public const PREFERRED_EXTENSION = [
        'image/jpeg'       => 'jpg',
        'image/tiff'       => 'tif',
        'text/javascript'  => 'js',
        'application/json' => 'json',
        'audio/ogg'        => 'ogg',
        'video/mp4'        => 'mp4',
        'text/plain'       => 'txt',
        'text/html'        => 'html',
        'audio/mp4'        => 'm4a',
    ];

    /**
     * Cac MIME KHONG duoc serve inline: trinh duyet se thuc thi script trong do,
     * gay stored XSS tren chinh domain cua ban.
     *
     * SVG la thu pham pho bien nhat - file .svg hop le co the chua <script>.
     *
     * @var list<string>
     */
    public const UNSAFE_INLINE = [
        'image/svg+xml',
        'text/html',
        'application/xhtml+xml',
        'application/xml',
        'text/xml',
        'text/x-php',
        'application/x-httpd-php',
        'text/javascript',
        'application/javascript',
        'application/xhtml',
        'application/rss+xml',
        'text/markdown',
    ];

    /**
     * Duoi file khong bao gio duoc chap nhan khi upload, du MIME co ve hop le.
     * Bao gom ca duoi kep kieu "anh.php.jpg" -> phai check tung phan.
     *
     * @var list<string>
     */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
        'pht', 'inc', 'hphp',
        'asp', 'aspx', 'ashx', 'asmx', 'cer', 'cshtml',
        'jsp', 'jspx', 'jsw', 'jsv', 'jspf',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
        'exe', 'dll', 'com', 'bat', 'cmd', 'msi', 'scr', 'vbs', 'ps1', 'jar',
        'htaccess', 'htpasswd', 'user', 'ini', 'config',
        'svg', 'html', 'htm', 'xhtml', 'shtml',
    ];

    /**
     * MIME anh cho phep kiem tra bo sung bang getimagesize().
     *
     * @var array<string, int>
     */
    public const IMAGE_TYPES = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png'  => IMAGETYPE_PNG,
        'image/gif'  => IMAGETYPE_GIF,
        'image/webp' => IMAGETYPE_WEBP,
        'image/bmp'  => IMAGETYPE_BMP,
        'image/tiff' => IMAGETYPE_TIFF_II,
    ];

    /** Bo type anh an toan, dung lam allowlist mac dinh cho avatar/anh san pham. */
    public const SAFE_IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Bo type tai lieu thuong dung. */
    public const DOCUMENTS = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private function __construct()
    {
    }
}
