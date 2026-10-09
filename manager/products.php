<?php
// ============================================================
// manager/products.php
// UC-06: xem và sửa thông tin sản phẩm, trạng thái kinh doanh.
// ============================================================

require_once __DIR__ . '/../includes/manager_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'Sản phẩm';
$activeNav = 'products';
require __DIR__ . '/../includes/layout/header_manager.php';
?>

        <div class="page-header">
            <h1>Quản lý sản phẩm</h1>
            <a href="/doan1/manager/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>


<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
