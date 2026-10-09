<?php
// ============================================================
// config/database.php
// File DUY NHẤT chứa thông tin kết nối database.
// Mọi file PHP khác trong project (login, sales, invoices, ...)
// đều phải require file này để lấy biến $pdo, KHÔNG được tự
// viết lại thông tin kết nối ở nơi khác.
// ============================================================

// ----- Thông tin kết nối MySQL (WAMP mặc định) -----
$db_host = "localhost";        // địa chỉ máy chủ MySQL
$db_name = "tinh_nhan";        // tên database đã import ở Bước 1
$db_user = "root";             // tài khoản MySQL mặc định của WAMP
$db_pass = "";                 // WAMP mặc định không có mật khẩu cho root
$db_charset = "utf8mb4";       // charset để hiển thị đúng tiếng Việt

// ----- Chuỗi DSN (Data Source Name) cho PDO -----
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// ----- Các tùy chọn cho PDO -----
$options = [
    // Khi có lỗi SQL, PDO sẽ ném ra Exception thay vì im lặng
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Kết quả truy vấn trả về dạng mảng kết hợp (associative array)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Không giả lập prepared statement, dùng prepared statement thật của MySQL
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ----- Thực hiện kết nối -----
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Nếu kết nối thất bại, dừng chương trình và báo lỗi rõ ràng.
    // Không hiển thị thông tin nhạy cảm (user/pass) ra ngoài.
    die("Lỗi kết nối database: " . $e->getMessage());
}

// Từ đây, bất kỳ file nào require 'config/database.php'
// đều có thể sử dụng biến $pdo để truy vấn database.
