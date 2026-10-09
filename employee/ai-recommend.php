<?php
// ============================================================
// employee/ai-recommend.php
// UC-10: đề xuất sản phẩm theo nhu cầu khách hàng (thuật toán KNN).
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// TODO: xử lý logic (truy vấn CSDL, kiểm tra dữ liệu đầu vào) viết ở đây,
// TRƯỚC khi require header vì header đã in HTML.

$pageTitle = 'AI đề xuất sản phẩm';
$activeNav = 'ai';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>AI đề xuất sản phẩm</h1>
            <a href="/doan1/employee/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
