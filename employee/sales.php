<?php
// ============================================================
// employee/sales.php
// UC-02, UC-04: lập hóa đơn bán hàng (trừ kho theo FIFO), thêm nhanh khách hàng.
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'Bán hàng';
$activeNav = 'sales';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Bán hàng</h1>
            <a href="/doan1/employee/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>



<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
