# TechZone

Website bán thiết bị điện tử viết bằng PHP thuần, PDO/MySQL, HTML/CSS, JavaScript và Bootstrap 5. Ứng dụng hướng tới môi trường học tập/chạy local với XAMPP; chưa tích hợp cổng thanh toán trực tuyến.

## Yêu cầu

- Windows và XAMPP có Apache, MySQL/MariaDB, PHP 8.0 trở lên.
- Bật các extension PHP `pdo_mysql`, `fileinfo` và `mbstring`.
- Kết nối Internet để tải Bootstrap 5.3.3 và font giao diện từ CDN. Các trang vẫn có nội dung HTML khi offline nhưng kiểu dáng sẽ bị giới hạn.

## Cài đặt trên XAMPP

1. Cài XAMPP, mở XAMPP Control Panel và nhấn **Start** cho Apache và MySQL.
2. Đặt thư mục dự án vào:

   ```text
   C:\xampp\htdocs\techzone
   ```

3. Mở `http://localhost/phpmyadmin/`.
4. Chọn tab **Import**, chọn file `database.sql` trong thư mục dự án, rồi nhấn **Import**. File sẽ tạo database `techzone_db` cùng các bảng và dữ liệu mẫu.

   Chỉ import vào database mới/trống. Không import lặp lại vào database đang có dữ liệu cần giữ; script không xóa bảng hiện hữu và các email/SKU mẫu có ràng buộc duy nhất.

5. Mở [config/database.php](./config/database.php). Thiết lập mặc định là:

   - Host: `127.0.0.1`
   - Port: `3306`
   - Database: `techzone_db`
   - Username: `root`
   - Password: rỗng

   Đây là thiết lập XAMPP phổ biến. Nếu máy của bạn dùng thông tin khác, có thể đổi mặc định trong file hoặc đặt biến môi trường `TECHZONE_DB_HOST`, `TECHZONE_DB_PORT`, `TECHZONE_DB_NAME`, `TECHZONE_DB_USER`, `TECHZONE_DB_PASS`. Không commit mật khẩu thật vào mã nguồn.

6. Mở website tại `http://localhost/techzone/`.
7. Đăng nhập Admin tại `http://localhost/techzone/login.php`:

   - Email: `admin@techzone.local`
   - Mật khẩu ban đầu: `TechZone@2026`

   Mật khẩu trong database được lưu bằng hash. Hãy vào **Tài khoản → Đổi mật khẩu** ngay sau lần đăng nhập đầu tiên; không dùng mật khẩu mẫu trên website công khai.

## Sử dụng

### Sản phẩm và ảnh

- Các trang cửa hàng chỉ truy vấn sản phẩm đang hoạt động từ database.
- Admin có thể thêm, sửa, ngừng bán hoặc xóa sản phẩm tại `/admin/products.php`.
- Khi thêm/sửa, chọn danh mục, thương hiệu và nhập giá/tồn kho. Thông số kỹ thuật nhận JSON, ví dụ:

  ```json
  {"CPU":"Intel Core i5","RAM":"16 GB","Ổ cứng":"512 GB SSD"}
  ```

- Ảnh tải lên hỗ trợ JPEG, PNG và WebP, tối đa 5 MB. Hệ thống kiểm tra MIME/nội dung ảnh, đặt tên ngẫu nhiên và lưu trong `uploads/products/`. Không đặt tệp thực thi vào thư mục upload.
- Xóa danh mục/thương hiệu đang được sản phẩm sử dụng sẽ bị database từ chối. Sản phẩm đã xuất hiện trong đơn hàng được giữ lại dưới dạng ngừng kinh doanh thay vì xóa mất lịch sử.

### Tài khoản, giỏ hàng và đơn hàng

- Khách có thể mua hàng không cần tài khoản; cần đăng nhập để xem lịch sử đơn hàng.
- Giá, khuyến mãi và tồn kho được đọc lại từ database khi đặt hàng. Tạo đơn và trừ kho nằm trong cùng transaction.
- Phương thức thanh toán là COD hoặc chuyển khoản thủ công. Chuyển khoản thủ công không tự đánh dấu đã thanh toán. Chỉ xác nhận thanh toán khi cửa hàng đã thực nhận tiền.
- Phí vận chuyển hiện đặt là `0` trong `checkout.php`; cần điều chỉnh nghiệp vụ nếu cửa hàng áp dụng phí giao hàng.
- Admin chuyển trạng thái theo thứ tự hợp lệ. Hủy đơn trước khi giao sẽ hoàn lại tồn kho trong transaction; đơn đã giao/hủy không thể chuyển tiếp qua luồng thông thường.

## Cấu trúc chính

```text
techzone/
├── admin/                 # Dashboard và công cụ quản trị
├── assets/css/style.css   # Giao diện responsive
├── assets/js/main.js      # JavaScript giao diện
├── config/                # Cấu hình ứng dụng và PDO
├── includes/              # Header/footer, xác thực và helper
├── uploads/products/      # Ảnh sản phẩm đã tải lên
├── database.sql           # Database và dữ liệu mẫu
├── index.php              # Trang chủ
├── products.php           # Danh sách, lọc và phân trang
├── product-detail.php     # Chi tiết sản phẩm
├── cart.php               # Giỏ hàng session
├── checkout.php           # Tạo đơn và trừ tồn kho
├── login.php
├── register.php
├── profile.php
└── orders-history.php
```

## Bảo mật và lưu ý triển khai

- Truy vấn database dùng PDO prepared statements; dữ liệu xuất HTML được escape.
- Form thay đổi dữ liệu có CSRF token; trang quản trị kiểm tra role trực tiếp từ database.
- Mật khẩu dùng `password_hash()` / `password_verify()`. Vai trò admin không được nhận từ form đăng ký.
- Upload giới hạn loại MIME, dung lượng và tên tệp; `.htaccess` chặn thực thi tại thư mục upload.
- Lỗi kết nối database được ghi vào PHP error log; website chỉ hiển thị thông báo chung.
- Trước khi đưa lên Internet, hãy đổi mật khẩu admin mẫu, cấu hình mật khẩu database riêng, bật HTTPS, kiểm tra backup, quyền ghi thư mục upload và cấu hình Apache/PHP phù hợp. Đây là dự án nền tảng để phát triển, chưa phải cấu hình production hoàn chỉnh.

## Kiểm tra nhanh

Trong PowerShell:

```powershell
& 'C:\xampp\php\php.exe' -v
& 'C:\xampp\mysql\bin\mysql.exe' --version
```

Kiểm tra cú pháp toàn bộ file PHP:

```powershell
Get-ChildItem -Path 'C:\xampp\htdocs\techzone' -Filter '*.php' -File -Recurse |
    ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
```

Sau khi import database, thử lần lượt: mở trang chủ, lọc sản phẩm, đăng ký tài khoản, thêm sản phẩm vào giỏ, đặt COD, xem lịch sử đơn, đăng nhập admin và cập nhật trạng thái đơn.

## Xử lý lỗi thường gặp

- **Không thể kết nối cơ sở dữ liệu:** bảo đảm MySQL đang chạy; xác nhận database là `techzone_db`; kiểm tra host, port, username và password trong `config/database.php` hoặc biến môi trường.
- **Unknown database `techzone_db`:** import `database.sql` bằng phpMyAdmin.
- **Table already exists / email hoặc SKU trùng khi import:** kiểm tra bạn có đang import lặp vào database đã dùng hay không. Sao lưu dữ liệu trước khi sửa database; không xóa database nếu chưa chắc chắn.
- **Không lưu được ảnh:** kiểm tra extension `fileinfo`, quyền ghi của Apache vào `uploads/products/`, `upload_max_filesize` và `post_max_size` trong `php.ini`. Khởi động lại Apache sau khi đổi `php.ini`.
- **Lỗi 403 ở thư mục cấu hình/includes:** ứng dụng cố ý chặn truy cập trực tiếp đến các thư mục này. Hãy truy cập website qua các trang PHP công khai hoặc `/admin/`.
- **Lỗi 404 hoặc sai đường dẫn:** xác nhận thư mục nằm đúng `C:\xampp\htdocs\techzone` và mở `http://localhost/techzone/`. Nếu triển khai dưới đường dẫn khác, đặt biến môi trường `TECHZONE_BASE_URL` tương ứng.
- **Trang thiếu CSS/font:** kiểm tra Internet/CDN; ứng dụng không yêu cầu cài Composer hay npm.
- **Lỗi PHP:** bật `display_errors` chỉ trong môi trường local để tìm nguyên nhân; không bật hiển thị lỗi chi tiết khi triển khai công khai. Xem Apache/PHP error log trong thư mục `C:\xampp\apache\logs` và `C:\xampp\php\logs` nếu có.
