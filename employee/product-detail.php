<?php
// ============================================================
// employee/product-detail.php
// Xem thông tin chi tiết 1 sản phẩm (chỉ đọc).
// Chỉ hiển thị các trường phù hợp với quyền Nhân viên:
// mã, tên, loại, thương hiệu, giá bán, tồn kho, trạng thái.
// KHÔNG hiển thị giá nhập (thông tin nhạy cảm dành cho Quản lý).
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// ------------------------------------------------------------
// 1. Lấy và kiểm tra tham số id
// ------------------------------------------------------------

$productId = (int) ($_GET['id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    die("Mã sản phẩm không hợp lệ.");
}

// ------------------------------------------------------------
// 2. Truy vấn thông tin sản phẩm + tổng tồn kho
// ------------------------------------------------------------

$sql = "
    SELECT
        p.product_id,
        p.product_code,
        p.product_name,
        p.category,
        p.brand,
        p.sale_price,
        p.status,
        f.frame_type,
        f.color,
        f.material,
        f.purpose,
        COALESCE(SUM(b.quantity_remaining), 0) AS stock_quantity
    FROM products p
    LEFT JOIN product_batches b ON b.product_id = p.product_id
    LEFT JOIN product_features f ON f.product_id = p.product_id
    WHERE p.product_id = ? AND p.status = 'active'
    GROUP BY p.product_id, p.product_code, p.product_name, p.category, p.brand,
             p.sale_price, p.status, f.frame_type, f.color, f.material, f.purpose
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
    $stock = getStockStatus((int) $product['stock_quantity']);
    // product_features có quan hệ 1-1 (tùy chọn) với products - một số
    // sản phẩm có thể chưa được gán đặc trưng AI, LEFT JOIN nên các cột
    // frame_type/color/material/purpose có thể là NULL.
    $hasFeatures = $product['frame_type'] !== null;
}
?>
<?php
$pageTitle = 'Chi tiết sản phẩm';
$activeNav = 'products';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Chi tiết sản phẩm</h1>
            <a href="/doan1/employee/products.php" class="btn-secondary">← Quay về danh sách sản phẩm</a>
        </div>

        <?php if ($notFound): ?>

            <div class="empty-state">
                <p>Không tìm thấy sản phẩm này hoặc sản phẩm hiện không còn kinh doanh.</p>
            </div>

        <?php else: ?>

            <div class="detail-card">

                <div class="detail-head">
                    <div class="detail-eyebrow">
                        Mã <?php echo htmlspecialchars($product['product_code']); ?>
                        · <?php echo htmlspecialchars($product['brand'] ?? 'Không thương hiệu'); ?>
                    </div>
                    <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
                </div>

                <div class="detail-price">
                    <span class="label">Giá bán</span>
                    <span class="value"><?php echo formatCurrency((float) $product['sale_price']); ?></span>
                </div>

                <?php if ($hasFeatures): ?>
                    <div class="detail-section-label">Đặc trưng sản phẩm</div>
                    <div class="spec-grid">
                        <div class="spec-item">
                            <span class="spec-label">Kiểu gọng</span>
                            <span class="spec-value"><?php echo htmlspecialchars(formatFeatureLabel('frame_type', $product['frame_type'])); ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Màu sắc</span>
                            <span class="spec-value"><?php echo htmlspecialchars(formatFeatureLabel('color', $product['color'])); ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Chất liệu</span>
                            <span class="spec-value"><?php echo htmlspecialchars(formatFeatureLabel('material', $product['material'])); ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Mục đích sử dụng</span>
                            <span class="spec-value"><?php echo htmlspecialchars(formatFeatureLabel('purpose', $product['purpose'])); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="detail-section-label">Đặc trưng sản phẩm</div>
                    <p class="ai-intro">Sản phẩm này chưa được gán đặc trưng AI (product_features), nên chưa tham gia đề xuất KNN.</p>
                <?php endif; ?>

                <div class="spec-grid">
                    <div class="spec-item">
                        <span class="spec-label">Loại kính</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['category']); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Trạng thái</span>
                        <span class="spec-value"><?php echo $product['status'] === 'active' ? 'Đang kinh doanh' : 'Ngừng kinh doanh'; ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Tồn kho</span>
                        <span class="spec-value">
                            <?php echo (int) $product['stock_quantity']; ?> sản phẩm
                            <span class="stock-badge <?php echo $stock['css_class']; ?>">
                                <?php echo $stock['label']; ?>
                            </span>
                        </span>
                    </div>
                </div>

            </div>

        <?php endif; ?>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
