<?php
// ============================================================
// employee/index.php
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// Đếm thử để kiểm tra CSDL đã import và kết nối được
$totalProducts  = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalInvoices  = (int) $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();

$pageTitle = 'Dashboard Nhân viên';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="welcome-box">
            <h1>Chào <?php echo htmlspecialchars($_SESSION['full_name']); ?>,</h1>
            <p>Khu vực dành cho Nhân viên - hôm nay là <?php echo htmlspecialchars(date('d/m/Y')); ?>.</p>
        </div>

        <div class="dash-section">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Sản phẩm đang kinh doanh</div>
                    <div class="kpi-value"><?php echo $totalProducts; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Khách hàng</div>
                    <div class="kpi-value"><?php echo $totalCustomers; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Hóa đơn</div>
                    <div class="kpi-value"><?php echo $totalInvoices; ?></div>
                </div>
            </div>
        </div>

        <div class="dash-section">
            <div class="dash-section-head">
                <h2>Chức năng chính</h2>
            </div>
            <div class="function-grid">
                <a href="/doan1/employee/sales.php" class="function-card">
                    <h3>Bán hàng</h3>
                    <p>UC-02, UC-04: lập hóa đơn, thêm khách hàng.</p>
                </a>
                <a href="/doan1/employee/products.php" class="function-card">
                    <h3>Sản phẩm</h3>
                    <p>UC-05: tìm kiếm, xem sản phẩm và tồn kho.</p>
                </a>
                <a href="/doan1/employee/invoices.php" class="function-card">
                    <h3>Hóa đơn</h3>
                    <p>UC-03: tra cứu hóa đơn đã lập.</p>
                </a>
                <a href="/doan1/employee/customers.php" class="function-card">
                    <h3>Khách hàng</h3>
                    <p>UC-04: xem và thêm khách hàng.</p>
                </a>
            </div>
        </div>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
