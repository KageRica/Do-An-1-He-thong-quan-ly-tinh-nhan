<?php
// ============================================================
// includes/layout/header_employee.php
// Header dùng chung cho toàn bộ trang khu vực Nhân viên.
//
// Cách dùng (đặt ở đầu mỗi trang employee/*.php, SAU khi đã
// require employee_guard.php và các include khác, và SAU khi đã
// xử lý xong logic PHP của trang vì header này đã in ra HTML):
//
//   $pageTitle = 'Bán hàng';        // hiển thị trong <title>
//   $activeNav = 'sales';           // tô đậm đúng mục nav hiện tại
//   require __DIR__ . '/../includes/layout/header_employee.php';
//
// $activeNav nhận một trong các giá trị: 'sales', 'products',
// 'invoices', 'customers', 'ai', hoặc '' (không tô đậm mục nào -
// áp dụng cho trang Dashboard vì Dashboard không nằm trong nav).
// ============================================================

if (!isset($pageTitle)) {
    $pageTitle = 'Tinh Nhãn';
}
if (!isset($activeNav)) {
    $activeNav = '';
}

if (!function_exists('navClass')) {
    function navClass($key, $activeNav)
    {
        return $key === $activeNav ? ' class="is-active"' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Tinh Nhãn</title>
    <link rel="stylesheet" href="/doan1/assets/css/style.css?v=b10">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Thanh header trên cùng -->
    <div class="topbar">
        <div class="topbar-inner">
            <div class="brand">Tinh Nhãn</div>
            <nav class="topnav">
                <a href="/doan1/employee/index.php"<?php echo navClass('dashboard', $activeNav); ?>>Dashboard</a>
                <a href="/doan1/employee/sales.php"<?php echo navClass('sales', $activeNav); ?>>Bán hàng</a>
                <a href="/doan1/employee/products.php"<?php echo navClass('products', $activeNav); ?>>Sản phẩm</a>
                <a href="/doan1/employee/invoices.php"<?php echo navClass('invoices', $activeNav); ?>>Hóa đơn</a>
                <a href="/doan1/employee/customers.php"<?php echo navClass('customers', $activeNav); ?>>Khách hàng</a>
                <a href="/doan1/employee/ai-recommend.php"<?php echo navClass('ai', $activeNav); ?>>AI đề xuất</a>
            </nav>
            <div class="user-info">
                <span>
                    Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                </span>
                <span class="role-badge">Nhân viên</span>
                <a href="/doan1/logout.php" class="btn-logout">Đăng xuất</a>
            </div>
        </div>
    </div>

    <div class="main-content">
