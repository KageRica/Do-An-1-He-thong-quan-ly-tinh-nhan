<?php
// ============================================================
// includes/auth.php
// Các hàm dùng chung cho việc kiểm tra đăng nhập và phân quyền.
// File này KHÔNG tự ý require config/database.php vì bản thân
// các hàm ở đây chỉ làm việc với session, không truy vấn database.
// Mọi file muốn dùng các hàm bên dưới chỉ cần require file này.
// ============================================================

// Khởi động session nếu chưa được khởi động ở đâu đó trước rồi.
// Kiểm tra session_status() để tránh lỗi "session already started".
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra người dùng đã đăng nhập hay chưa.
 * Dựa vào sự tồn tại của $_SESSION['user_id'].
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Bắt buộc người dùng phải đăng nhập.
 * Nếu chưa đăng nhập -> chuyển hướng về login.php và dừng script.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: /doan1/login.php");
        exit();
    }
}

/**
 * Bắt buộc người dùng phải đăng nhập VÀ đúng role được truyền vào.
 * Nếu chưa đăng nhập -> về login.php.
 * Nếu đã đăng nhập nhưng sai role -> chặn truy cập (không cho vào khu vực đó).
 *
 * @param string $role  'employee' hoặc 'manager'
 */
function requireRole(string $role): void
{
    // Bước 1: phải đăng nhập trước
    requireLogin();

    // Bước 2: kiểm tra đúng role
    if ($_SESSION['role'] !== $role) {
        // Không đúng quyền -> không cho xem nội dung trang này.
        // Trả về mã lỗi 403 (Forbidden) đúng chuẩn HTTP, kèm thông báo.
        http_response_code(403);
        die("Bạn không có quyền truy cập chức năng này.");
    }
}

/**
 * Đăng xuất: xóa toàn bộ dữ liệu session và hủy session hiện tại.
 * Không tự động chuyển hướng ở đây, việc chuyển hướng do file
 * logout.php gọi hàm này thực hiện, để hàm giữ nhiệm vụ đơn giản.
 */
function doLogout(): void
{
    // Xóa toàn bộ biến session
    $_SESSION = [];

    // Xóa cookie session ở phía trình duyệt (nếu có)
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Hủy session trên server
    session_destroy();
}
