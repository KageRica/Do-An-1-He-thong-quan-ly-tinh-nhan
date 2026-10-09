<?php
// ============================================================
// index.php
// Trang gốc của project (http://localhost/doan1/).
// KHÔNG chứa nghiệp vụ - chỉ điều hướng người dùng:
//   - Đã đăng nhập -> chuyển đến đúng khu vực theo role.
//   - Chưa đăng nhập -> chuyển đến login.php.
// Logic điều hướng tương tự đoạn code đã có sẵn trong login.php.
// ============================================================

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] === 'employee') {
        header("Location: /doan1/employee/index.php");
        exit();
    } elseif ($_SESSION['role'] === 'manager') {
        header("Location: /doan1/manager/index.php");
        exit();
    }
}

header("Location: /doan1/login.php");
exit();
