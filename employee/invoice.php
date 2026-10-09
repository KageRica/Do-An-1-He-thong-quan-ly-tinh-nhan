<?php
// ============================================================
// employee/invoice.php
// UC-03: xem chi tiết một hóa đơn.
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'Chi tiết hóa đơn';
$activeNav = 'invoices';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Chi tiết hóa đơn</h1>
            <a href="/doan1/employee/invoices.php" class="btn-secondary">← Quay về danh sách</a>
        </div>


<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
