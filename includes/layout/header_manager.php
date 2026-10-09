<?php
// ============================================================
// includes/layout/header_manager.php
// Header dùng chung cho toàn bộ trang khu vực Quản lý.
//
// Cách dùng (đặt ở đầu mỗi trang manager/*.php, SAU khi đã
// require manager_guard.php và các include khác, và SAU khi đã
// xử lý xong logic PHP của trang vì header này đã in ra HTML):
//
//   $pageTitle = 'Nhập kho';        // hiển thị trong <title>
//   $activeNav = 'import';          // tô đậm đúng mục nav hiện tại
//   require __DIR__ . '/../includes/layout/header_manager.php';
//
// $activeNav nhận một trong các giá trị: 'dashboard', 'products',
// 'import', 'accounts', 'reports'.
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

    <div class="topbar">
        <div class="topbar-inner">
            <div class="brand">Tinh Nhãn</div>
            <nav class="topnav">
                <a href="/doan1/manager/index.php"<?php echo navClass('dashboard', $activeNav); ?>>Dashboard</a>
                <a href="/doan1/manager/products.php"<?php echo navClass('products', $activeNav); ?>>Sản phẩm</a>
                <a href="/doan1/manager/import.php"<?php echo navClass('import', $activeNav); ?>>Nhập kho</a>
                <a href="/doan1/manager/accounts.php"<?php echo navClass('accounts', $activeNav); ?>>Tài khoản</a>
                <a href="/doan1/manager/reports.php"<?php echo navClass('reports', $activeNav); ?>>Báo cáo</a>
            </nav>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <span class="role-badge">Quản lý</span>
                <a href="/doan1/logout.php" class="btn-logout">Đăng xuất</a>
            </div>
        </div>
    </div>

    <div class="main-content">
