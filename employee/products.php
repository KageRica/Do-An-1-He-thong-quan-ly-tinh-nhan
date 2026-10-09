<?php

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

// 1. Lấy và kiểm tra tham số đầu vào (GET)

// Từ khóa tìm kiếm (mã / tên / thương hiệu)
$keyword = trim($_GET['keyword'] ?? '');

// Trang hiện tại, ép kiểu int và đảm bảo tối thiểu là 1
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}

// Số sản phẩm hiển thị mỗi trang
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 2. Xây dựng câu điều kiện tìm kiếm (dùng chung cho cả 2 truy vấn)

$whereSql = "WHERE p.status = 'active'";
$params = [];

if ($keyword !== '') {
    $whereSql .= " AND (p.product_code LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?)";
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

// 3. Đếm tổng số sản phẩm phù hợp (phục vụ phân trang)

$countSql = "SELECT COUNT(*) FROM products p " . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalProducts / $perPage);

// Nếu người dùng truyền page vượt quá số trang thực tế, giữ nguyên
// giá trị offset an toàn (không để offset âm hoặc query lỗi).
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// 4. Truy vấn danh sách sản phẩm + tồn kho SUM từ product_batches

$listSql = "
    SELECT
        p.product_id,
        p.product_code,
        p.product_name,
        p.category,
        p.brand,
        p.sale_price,
        p.status,
        COALESCE(SUM(b.quantity_remaining), 0) AS stock_quantity
    FROM products p
    LEFT JOIN product_batches b ON b.product_id = p.product_id
    $whereSql
    GROUP BY p.product_id, p.product_code, p.product_name, p.category, p.brand, p.sale_price, p.status
    ORDER BY p.product_name ASC
    LIMIT ? OFFSET ?
";

$listStmt = $pdo->prepare($listSql);

// Bind từng tham số tìm kiếm (nếu có)
$paramIndex = 1;
foreach ($params as $paramValue) {
    $listStmt->bindValue($paramIndex, $paramValue, PDO::PARAM_STR);
    $paramIndex++;
}
// Bind LIMIT / OFFSET dưới dạng số nguyên (bắt buộc khi EMULATE_PREPARES = false)
$listStmt->bindValue($paramIndex, $perPage, PDO::PARAM_INT);
$paramIndex++;
$listStmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

$listStmt->execute();
$products = $listStmt->fetchAll();
?>
<?php
$pageTitle = 'Sản phẩm';
$activeNav = 'products';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Sản phẩm</h1>
            <a href="/doan1/employee/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>

        <!-- Ô tìm kiếm -->
        <form method="GET" action="products.php" class="search-form">
            <input
                type="text"
                name="keyword"
                placeholder="Tìm theo mã, tên hoặc thương hiệu..."
                value="<?php echo htmlspecialchars($keyword); ?>"
            >
            <button type="submit" class="btn-primary">Tìm kiếm</button>
            <?php if ($keyword !== ''): ?>
                <a href="/doan1/employee/products.php" class="btn-secondary">Xóa lọc</a>
            <?php endif; ?>
        </form>

        <!-- Danh sách sản phẩm -->
        <?php if (empty($products)): ?>

            <div class="empty-state">
                <?php if ($keyword !== ''): ?>
                    <p>Không tìm thấy sản phẩm nào phù hợp với từ khóa "<?php echo htmlspecialchars($keyword); ?>".</p>
                <?php else: ?>
                    <p>Hiện chưa có sản phẩm nào trong hệ thống.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <p class="result-count">Tìm thấy <?php echo $totalProducts; ?> sản phẩm<?php echo $keyword !== '' ? ' phù hợp' : ''; ?>.</p>

            <table class="product-table">
                <thead>
                    <tr>
                        <th>Mã SP</th>
                        <th>Tên sản phẩm</th>
                        <th>Loại kính</th>
                        <th>Thương hiệu</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php $stock = getStockStatus((int) $product['stock_quantity']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo htmlspecialchars($product['brand'] ?? ''); ?></td>
                            <td><?php echo formatCurrency((float) $product['sale_price']); ?></td>
                            <td>
                                <?php echo (int) $product['stock_quantity']; ?>
                                <span class="stock-badge <?php echo $stock['css_class']; ?>">
                                    <?php echo $stock['label']; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $product['status'] === 'active' ? 'Đang kinh doanh' : 'Ngừng kinh doanh'; ?>
                            </td>
                            <td>
                                <a href="/doan1/employee/product-detail.php?id=<?php echo (int) $product['product_id']; ?>" class="btn-link">
                                    Xem chi tiết
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Phân trang -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?keyword=<?php echo urlencode($keyword); ?>&page=<?php echo $page - 1; ?>" class="btn-secondary">« Trang trước</a>
                    <?php endif; ?>

                    <span class="page-info">Trang <?php echo $page; ?> / <?php echo $totalPages; ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?keyword=<?php echo urlencode($keyword); ?>&page=<?php echo $page + 1; ?>" class="btn-secondary">Trang sau »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
