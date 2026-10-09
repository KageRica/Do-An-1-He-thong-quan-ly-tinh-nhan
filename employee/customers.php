<?php
require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';
// 1. Xử lý THÊM khách hàng mới (khi có submit form POST)
$errorMessage = '';
$successMessage = '';

// Giá trị nhập tạm để hiển thị lại form nếu có lỗi (không mất dữ liệu đã gõ)
$formFullName = '';
$formPhone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    $formFullName = trim($_POST['full_name'] ?? '');
    $formPhone = trim($_POST['phone'] ?? '');

    if ($formFullName === '' || $formPhone === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ Họ tên và Số điện thoại.';
    } elseif (!isValidPhone($formPhone)) {
        $errorMessage = 'Số điện thoại không đúng định dạng (chỉ gồm chữ số, 9-15 ký tự).';
    } else {
        // Kiểm tra trùng số điện thoại trước khi INSERT (phone có UNIQUE trong DB)
        $checkStmt = $pdo->prepare("SELECT customer_id FROM customers WHERE phone = ?");
        $checkStmt->execute([$formPhone]);
        if ($checkStmt->fetch()) {
            $errorMessage = 'Số điện thoại này đã tồn tại trong hệ thống.';
        } else {
            try {
                $insertStmt = $pdo->prepare("INSERT INTO customers (full_name, phone) VALUES (?, ?)");
                $insertStmt->execute([$formFullName, $formPhone]);
                // Thêm thành công -> redirect (PRG pattern) để tránh submit lại khi F5
                header("Location: /doan1/employee/customers.php?msg=added");
                exit();
            } catch (PDOException $e) {
                // Phòng trường hợp race-condition trùng UNIQUE phone
                // giữa lúc kiểm tra và lúc INSERT thực tế.
                $errorMessage = 'Số điện thoại này đã tồn tại trong hệ thống.';
            }
        }
    }
}

// Thông báo thành công sau khi redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $successMessage = 'Thêm khách hàng thành công.';
}
// 2. Tìm kiếm + phân trang danh sách khách hàng (GET)
$keyword = trim($_GET['keyword'] ?? '');
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereSql = '';
$params = [];

if ($keyword !== '') {
    $whereSql = 'WHERE full_name LIKE ? OR phone LIKE ?';
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

// Đếm tổng số khách hàng phù hợp (phục vụ phân trang)
$countSql = "SELECT COUNT(*) FROM customers $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCustomers = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalCustomers / $perPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
    SELECT customer_id, full_name, phone, created_at
    FROM customers
    $whereSql
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
$listStmt = $pdo->prepare($listSql);

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
$customers = $listStmt->fetchAll();
?>
<?php
$pageTitle = 'Khách hàng';
$activeNav = 'customers';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Khách hàng</h1>
            <a href="/doan1/employee/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- Ô tìm kiếm -->
        <form method="GET" action="customers.php" class="search-form">
            <input
                type="text"
                name="keyword"
                placeholder="Tìm theo họ tên hoặc số điện thoại..."
                value="<?php echo htmlspecialchars($keyword); ?>"
            >
            <button type="submit" class="btn-primary">Tìm kiếm</button>
            <?php if ($keyword !== ''): ?>
                <a href="/doan1/employee/customers.php" class="btn-secondary">Xóa lọc</a>
            <?php endif; ?>
        </form>

        <!-- Danh sách khách hàng -->
        <?php if (empty($customers)): ?>

            <div class="empty-state">
                <?php if ($keyword !== ''): ?>
                    <p>Không tìm thấy khách hàng nào phù hợp với từ khóa "<?php echo htmlspecialchars($keyword); ?>".</p>
                <?php else: ?>
                    <p>Hiện chưa có khách hàng nào trong hệ thống.</p>
                    <a href="#add-customer-form" class="btn-primary">+ Thêm khách hàng đầu tiên</a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <p class="result-count">Tìm thấy <?php echo $totalCustomers; ?> khách hàng<?php echo $keyword !== '' ? ' phù hợp' : ''; ?>.</p>

            <table class="product-table">
                <thead>
                    <tr>
                        <th>Mã KH</th>
                        <th>Họ tên</th>
                        <th>Số điện thoại</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo formatCustomerCode((int) $customer['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($customer['created_at']))); ?></td>
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

        <!-- Thêm khách hàng -->
        <div class="form-card" id="add-customer-form">
            <h2>Thêm khách hàng mới</h2>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <form method="POST" action="customers.php#add-customer-form" class="customer-form">
                <input type="hidden" name="action" value="add_customer">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Họ tên <span class="required">*</span></label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?php echo htmlspecialchars($formFullName); ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="phone">Số điện thoại <span class="required">*</span></label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?php echo htmlspecialchars($formPhone); ?>"
                            required
                        >
                    </div>
                </div>
                <button type="submit" class="btn-primary">Thêm khách hàng</button>
            </form>
        </div>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
