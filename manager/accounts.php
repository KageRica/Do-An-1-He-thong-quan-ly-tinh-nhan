<?php
// ============================================================
// manager/accounts.php
// UC-08: tạo tài khoản, phân quyền, khóa/mở khóa.
// ============================================================

require_once __DIR__ . '/../includes/manager_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'Tài khoản';
$activeNav = 'accounts';
require __DIR__ . '/../includes/layout/header_manager.php';
?>

        <div class="page-header">
            <h1>Tài khoản & phân quyền</h1>
            <a href="/doan1/manager/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>


<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
