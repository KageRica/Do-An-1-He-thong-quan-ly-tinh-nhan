<?php
// ============================================================
// employee/product-detail.php
// UC-05: xem thông tin chi tiết một sản phẩm.
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'Chi tiết sản phẩm';
$activeNav = 'products';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Chi tiết sản phẩm</h1>
            <a href="/doan1/employee/products.php" class="btn-secondary">← Quay về danh sách</a>
        </div>



<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
