# nguyenquocanhz/mime-type

[![CI](https://github.com/nguyenquocanhz/php-mime-type/actions/workflows/ci.yml/badge.svg)](https://github.com/nguyenquocanhz/php-mime-type/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/nguyenquocanhz/mime-type.svg)](https://packagist.org/packages/nguyenquocanhz/mime-type)
[![License](https://img.shields.io/packagist/l/nguyenquocanhz/mime-type.svg)](LICENSE)

Xác định và kiểm tra MIME type an toàn cho PHP 8.1+.

Thay thế `mime_content_type()` với 3 khác biệt chính:

| | `mime_content_type()` | `MimeType` |
|---|---|---|
| `.css` / `.js` | `text/plain` ❌ | `text/css` / `text/javascript` ✅ |
| File lỗi | `false` + `E_WARNING` | `MimeException` |
| `phar://`, `../../etc/passwd` | Xử lý bình thường ❌ | Bị chặn ✅ |

## Cài đặt

```bash
composer require nguyenquocanhz/mime-type
```

Namespace là `Gems\Mime\` (không trùng tên vendor — Composer không bắt buộc phải trùng).

Chưa có Composer thì `require` autoloader dự phòng:

```php
require '/duong/dan/php-mime/autoload.php';
```

Yêu cầu: PHP >= 8.1, `ext-fileinfo`.

## Dùng nhanh

```php
use Gems\Mime\MimeType;
use Gems\Mime\MimeMap;

$mime = new MimeType();
$mime->fromPath('/var/www/app.css');   // text/css
$mime->detect('/tmp/upload');          // đọc magic bytes, bỏ qua đuôi file
$mime->fromBuffer($blob);              // cho dữ liệu trong RAM
```

## Serve file

`headersFor()` luôn thêm `X-Content-Type-Options: nosniff`, và tự ép tải xuống
với các type chạy được script (SVG, HTML, XML) để chặn stored XSS.

```php
$mime = new MimeType(baseDir: __DIR__ . '/uploads');   // khóa trong thư mục này

$mime->sendHeaders($path);
readfile($path);
```

Kết quả với `.svg`:

```
Content-Type: application/octet-stream
Content-Disposition: attachment; filename="anh.svg"
X-Content-Type-Options: nosniff
```

## Kiểm tra file upload

```php
use Gems\Mime\MimeException;

try {
    $name = $mime->validateUpload($_FILES['avatar'], MimeMap::SAFE_IMAGES, maxBytes: 2 * 1024 * 1024);
    move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . "/uploads/$name");
} catch (MimeException $e) {
    error_log($e->getMessage());   // chỉ log, đừng echo — chứa đường dẫn server
    http_response_code(422);
    exit('File không hợp lệ');
}
```

`validateUpload()` làm 6 bước:

1. Kiểm tra `UPLOAD_ERR_OK` và `is_uploaded_file()`
2. Chặn file rỗng và file quá `maxBytes`
3. Chặn đuôi nguy hiểm, **kể cả đuôi kép** `avatar.php.jpg`
4. Đọc MIME thật từ magic bytes, đối chiếu allowlist
5. Với ảnh: kiểm tra thêm `getimagesize()` để loại file polyglot
6. Sinh tên ngẫu nhiên, đuôi lấy từ MIME thật — không giữ gì từ client

## Những gì thư viện KHÔNG làm

- **Không** thay thế việc lưu file upload ngoài web root. MIME hợp lệ vẫn có thể
  kèm payload; cách chắc chắn nhất là để file ở nơi Apache không execute được.
- **Không** chống được server config sai. Nếu Apache có `AddHandler` cho đuôi kép
  thì `.jpg` vẫn chạy như PHP — phải sửa ở vhost.
- **Không** quét virus. Cần ClamAV hoặc tương đương cho nội dung file.

## Bảo mật

Nếu `baseDir` không được đặt, mọi đường dẫn đều được chấp nhận miễn tồn tại.
**Luôn đặt `baseDir` khi đường dẫn có thể đến từ request.**

```php
// ❌ LFI
$mime = new MimeType();
$mime->sendHeaders($_GET['file']);

// ✅
$mime = new MimeType(baseDir: __DIR__ . '/public/files');
$mime->sendHeaders(__DIR__ . '/public/files/' . basename($_GET['file']));
```

## Test

```bash
composer install
composer test
composer analyse
```

## License

MIT
