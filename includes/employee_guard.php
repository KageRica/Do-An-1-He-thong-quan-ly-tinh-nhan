<?php
// ============================================================
// includes/employee_guard.php
// Đặt dòng require_once file này ở ĐẦU mỗi file thuộc khu vực
// employee/ để chặn truy cập trái phép.
// Yêu cầu: đã đăng nhập VÀ role phải là 'employee'.
// Kiểm tra ở phía SERVER, không phụ thuộc ẩn/hiện bằng CSS/JS.
// ============================================================

require_once __DIR__ . '/auth.php';

requireRole('employee');
