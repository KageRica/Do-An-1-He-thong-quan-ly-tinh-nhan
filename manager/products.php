<?php
// ============================================================
// manager/products.php
// UC-06: Quản lý sản phẩm.
// ============================================================

require_once __DIR__ . '/../includes/manager_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';

// ------------------------------------------------------------
// AJAX: Lấy danh sách sản phẩm (có thể lọc theo từ khóa)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'list_products') {
    header('Content-Type: application/json; charset=utf-8');

    $keyword = trim($_POST['keyword'] ?? '');

    $whereSql = '';
    $params = [];
    if ($keyword !== '') {
        $whereSql = "WHERE p.product_code LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ? OR p.category LIKE ?";
        $like = '%' . $keyword . '%';
        $params = [$like, $like, $like, $like];
    }

    $sql = "
        SELECT
            p.product_id, p.product_code, p.product_name, p.category, p.brand,
            p.import_price, p.sale_price, p.status,
            COALESCE(SUM(b.quantity_remaining), 0) AS stock_quantity
        FROM products p
        LEFT JOIN product_batches b ON b.product_id = p.product_id
        $whereSql
        GROUP BY p.product_id, p.product_code, p.product_name, p.category, p.brand, p.import_price, p.sale_price, p.status
        ORDER BY p.product_name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $stock = getStockStatus((int) $row['stock_quantity']);
        $result[] = [
            'product_id'     => (int) $row['product_id'],
            'product_code'   => $row['product_code'],
            'product_name'   => $row['product_name'],
            'category'       => $row['category'],
            'brand'          => $row['brand'],
            'import_price'   => (float) $row['import_price'],
            'sale_price'     => (float) $row['sale_price'],
            'status'         => $row['status'],
            'stock_quantity' => (int) $row['stock_quantity'],
            'stock_label'    => $stock['label'],
            'stock_class'    => $stock['css_class'],
        ];
    }

    echo json_encode(['success' => true, 'products' => $result], JSON_UNESCAPED_UNICODE);
    exit();
}

// ------------------------------------------------------------
// AJAX: Cập nhật thông tin sản phẩm (tên, loại, thương hiệu, giá bán, trạng thái)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_product') {
    header('Content-Type: application/json; charset=utf-8');

    $productId = (int) ($_POST['product_id'] ?? 0);
    $name      = trim($_POST['product_name'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $brand     = trim($_POST['brand'] ?? '');
    $salePrice = $_POST['sale_price'] ?? '';
    $status    = $_POST['status'] ?? '';

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($name === '' || $category === '') {
        echo json_encode(['success' => false, 'message' => 'Tên sản phẩm và Loại sản phẩm không được để trống.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (!is_numeric($salePrice) || (float) $salePrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Giá bán phải lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $salePrice = (float) $salePrice;

    try {
        // Lấy import_price hiện tại để đảm bảo vẫn giữ đúng ràng buộc sale_price > import_price
        $stmt = $pdo->prepare("SELECT import_price FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        $current = $stmt->fetch();

        if (!$current) {
            echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Giá bán phải lớn hơn giá nhập gần nhất (ràng buộc của bảng products)
        // VÀ giá nhập cao nhất trong các lô còn hàng: FIFO xuất lô cũ trước nên
        // nếu lô cũ đắt hơn lô mới thì không được hạ giá bán xuống dưới vốn lô cũ.
        $costStmt = $pdo->prepare("SELECT COALESCE(MAX(import_price), 0) FROM product_batches WHERE product_id = ? AND quantity_remaining > 0");
        $costStmt->execute([$productId]);
        $maxRemainingCost = (float) $costStmt->fetchColumn();
        $priceFloor = max((float) $current['import_price'], $maxRemainingCost);

        if ($salePrice <= $priceFloor) {
            echo json_encode([
                'success' => false,
                'message' => 'Giá bán phải lớn hơn giá nhập cao nhất của hàng đang tồn kho (' . formatCurrency($priceFloor) . ').',
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $pdo->prepare("
            UPDATE products
            SET product_name = ?, category = ?, brand = ?, sale_price = ?, status = ?
            WHERE product_id = ?
        ")->execute([$name, $category, $brand !== '' ? $brand : null, $salePrice, $status, $productId]);

        echo json_encode(['success' => true, 'message' => 'Cập nhật sản phẩm thành công.'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Có lỗi khi cập nhật: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}
?>
<?php
$pageTitle = 'Sản phẩm';
$activeNav = 'products';
require __DIR__ . '/../includes/layout/header_manager.php';
?>

        <div class="page-header">
            <h1>Sản phẩm</h1>
            <a href="/doan1/manager/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>

        <div class="sales-grid">

            <!-- CỘT TRÁI: danh sách sản phẩm -->
            <div class="sales-col">
                <div class="form-card">
                    <h2>Danh sách sản phẩm</h2>
                    <div class="search-form">
                        <input type="text" id="productKeyword" placeholder="Tìm theo mã, tên, thương hiệu, loại...">
                        <button type="button" class="btn-primary" id="btnSearchProduct">Tìm kiếm</button>
                    </div>
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Tên sản phẩm</th>
                                <th>Loại</th>
                                <th>Giá bán</th>
                                <th>Tồn</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <tr><td colspan="7" class="u-text-center">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CỘT PHẢI: form sửa sản phẩm đang chọn -->
            <div class="sales-col is-invoice">
                <div class="form-card">
                    <h2>Sửa thông tin sản phẩm</h2>
                    <div id="noSelectionNote" class="result-empty-inline">Bấm "Sửa" trên 1 sản phẩm ở danh sách bên trái.</div>

                    <div id="editForm" style="display:none;">
                        <p class="u-hint-text" style="font-size:13px; margin-bottom:12px;">
                            Đang sửa: <strong id="editProductCode"></strong>
                            &nbsp;|&nbsp; Giá nhập hiện tại (tham khảo, không sửa ở đây): <strong id="editImportPrice"></strong>
                        </p>
                        <div class="form-group">
                            <label for="editName">Tên sản phẩm</label>
                            <input type="text" id="editName">
                        </div>
                        <div class="form-group">
                            <label for="editCategory">Loại sản phẩm</label>
                            <input type="text" id="editCategory">
                        </div>
                        <div class="form-group">
                            <label for="editBrand">Thương hiệu</label>
                            <input type="text" id="editBrand">
                        </div>
                        <div class="form-group">
                            <label for="editSalePrice">Giá bán</label>
                            <input type="number" id="editSalePrice" min="1">
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Trạng thái</label>
                            <select id="editStatus">
                                <option value="active">Đang kinh doanh</option>
                                <option value="inactive">Ngừng kinh doanh</option>
                            </select>
                        </div>
                        <button type="button" class="btn-primary u-full-width" id="btnSaveProduct">Lưu thay đổi</button>
                        <div id="editMessage"></div>
                        <p class="u-hint-text" style="margin-top:12px;">
                            Cần nhập thêm hàng cho sản phẩm này? Qua trang <a href="/doan1/manager/import.php">Nhập kho</a>.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    <script>
    (function () {
        var editingProductId = null;

        function formatCurrency(amount) {
            var rounded = Math.round(amount);
            return rounded.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' đ';
        }

        function postAction(action, data) {
            var params = new URLSearchParams();
            params.append('action', action);
            for (var key in data) {
                if (Object.prototype.hasOwnProperty.call(data, key)) {
                    params.append(key, data[key]);
                }
            }
            return fetch('products.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            }).then(function (res) { return res.json(); });
        }

        function showMessage(containerId, message, isError) {
            var box = document.getElementById(containerId);
            box.innerHTML = '';
            var div = document.createElement('div');
            div.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
            div.textContent = message;
            box.appendChild(div);
        }

        function loadProducts() {
            var keyword = document.getElementById('productKeyword').value.trim();
            var body = document.getElementById('productTableBody');
            body.innerHTML = '<tr><td colspan="7" class="u-text-center">Đang tải...</td></tr>';

            postAction('list_products', { keyword: keyword }).then(function (res) {
                body.innerHTML = '';
                if (!res.success || res.products.length === 0) {
                    body.innerHTML = '<tr><td colspan="7" class="u-text-center">Không có sản phẩm phù hợp.</td></tr>';
                    return;
                }
                res.products.forEach(function (p) {
                    var tr = document.createElement('tr');

                    var statusLabel = p.status === 'active' ? 'Đang kinh doanh' : 'Ngừng kinh doanh';
                    var statusClass = p.status === 'active' ? 'stock-ok' : 'stock-out';

                    tr.innerHTML =
                        '<td>' + p.product_code + '</td>' +
                        '<td>' + p.product_name + '</td>' +
                        '<td>' + (p.category || '') + '</td>' +
                        '<td>' + formatCurrency(p.sale_price) + '</td>' +
                        '<td><span class="stock-badge ' + p.stock_class + '">' + p.stock_quantity + '</span></td>' +
                        '<td><span class="stock-badge ' + statusClass + '">' + statusLabel + '</span></td>' +
                        '<td></td>';

                    var actionCell = tr.querySelector('td:last-child');
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn-link';
                    btn.textContent = 'Sửa';
                    btn.addEventListener('click', function () { editProduct(p); });
                    actionCell.appendChild(btn);

                    body.appendChild(tr);
                });
            });
        }

        function editProduct(p) {
            editingProductId = p.product_id;
            document.getElementById('noSelectionNote').style.display = 'none';
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('editProductCode').textContent = p.product_code;
            document.getElementById('editImportPrice').textContent = formatCurrency(p.import_price);
            document.getElementById('editName').value = p.product_name;
            document.getElementById('editCategory').value = p.category || '';
            document.getElementById('editBrand').value = p.brand || '';
            document.getElementById('editSalePrice').value = p.sale_price;
            document.getElementById('editStatus').value = p.status;
            document.getElementById('editMessage').innerHTML = '';
        }

        document.getElementById('btnSearchProduct').addEventListener('click', loadProducts);
        document.getElementById('productKeyword').addEventListener('keyup', function (e) {
            if (e.key === 'Enter') loadProducts();
        });

        document.getElementById('btnSaveProduct').addEventListener('click', function () {
            if (!editingProductId) return;
            postAction('update_product', {
                product_id: editingProductId,
                product_name: document.getElementById('editName').value,
                category: document.getElementById('editCategory').value,
                brand: document.getElementById('editBrand').value,
                sale_price: document.getElementById('editSalePrice').value,
                status: document.getElementById('editStatus').value
            }).then(function (res) {
                showMessage('editMessage', res.message, !res.success);
                if (res.success) {
                    loadProducts();
                }
            });
        });

        // Hiển thị danh sách ngay khi mở trang, không cần gõ tìm trước
        loadProducts();
    })();
    </script>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
