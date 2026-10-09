<?php
// ============================================================
// employee/invoices.php
// UC-03: tra cứu danh sách hóa đơn đã lập.
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'Hóa đơn';
$activeNav = 'invoices';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Tra cứu hóa đơn</h1>
            <a href="/doan1/employee/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>


<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
