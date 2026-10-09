<?php
// ============================================================
// manager/index.php
// ============================================================

require_once __DIR__ . '/../includes/manager_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// Đếm thử để kiểm tra CSDL đã import và kết nối được
$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalBatches  = (int) $pdo->query("SELECT COUNT(*) FROM product_batches")->fetchColumn();
$totalUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

$pageTitle = 'Dashboard Quản lý';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/layout/header_manager.php';
?>

        <div class="welcome-box">
            <h1>Chào <?php echo htmlspecialchars($_SESSION['full_name']); ?>,</h1>
            <p>Khu vực dành cho Quản lý - hôm nay là <?php echo htmlspecialchars(date('d/m/Y')); ?>.</p>
        </div>

        <div class="dash-section">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Sản phẩm</div>
                    <div class="kpi-value"><?php echo $totalProducts; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Lô hàng</div>
                    <div class="kpi-value"><?php echo $totalBatches; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Tài khoản hoạt động</div>
                    <div class="kpi-value"><?php echo $totalUsers; ?></div>
                </div>
            </div>
        </div>

        <div class="dash-section">
            <div class="dash-section-head">
                <h2>Chức năng chính</h2>
            </div>
            <div class="function-grid">
                <a href="/doan1/manager/products.php" class="function-card">
                    <h3>Sản phẩm</h3>
                    <p>UC-06: xem, sửa thông tin sản phẩm.</p>
                </a>
                <a href="/doan1/manager/import.php" class="function-card">
                    <h3>Nhập kho</h3>
                    <p>UC-07: nhập lô hàng, tạo sản phẩm mới.</p>
                </a>
                <a href="/doan1/manager/accounts.php" class="function-card">
                    <h3>Tài khoản</h3>
                    <p>UC-08: tạo tài khoản, phân quyền, khóa/mở khóa.</p>
                </a>
                <a href="/doan1/manager/reports.php" class="function-card">
                    <h3>Báo cáo doanh thu</h3>
                    <p>UC-09: doanh thu, lợi nhuận theo thời gian.</p>
                </a>
            </div>
        </div>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
