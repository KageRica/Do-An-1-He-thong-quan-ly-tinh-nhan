<?php
// ============================================================
// manager/import.php
// UC-07: nhập thêm lô hàng, tạo sản phẩm mới.
// ============================================================

require_once __DIR__ . '/../includes/manager_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';

// ------------------------------------------------------------
// AJAX: Tìm sản phẩm theo mã/tên/thương hiệu (để nhập thêm lô)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'search_product') {
    header('Content-Type: application/json; charset=utf-8');

    $keyword = trim($_POST['keyword'] ?? '');

    $whereSql = '';
    $params = [];
    if ($keyword !== '') {
        $whereSql = "WHERE p.product_code LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?";
        $like = '%' . $keyword . '%';
        $params = [$like, $like, $like];
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
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
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
        ];
    }

    echo json_encode(['success' => true, 'products' => $result], JSON_UNESCAPED_UNICODE);
    exit();
}

// ------------------------------------------------------------
// AJAX: Nhập thêm 1 lô cho sản phẩm đã có sẵn
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_batch') {
    header('Content-Type: application/json; charset=utf-8');

    $productId    = (int) ($_POST['product_id'] ?? 0);
    $quantity     = (int) ($_POST['quantity'] ?? 0);
    $importPrice  = $_POST['import_price'] ?? '';
    $importDate   = trim($_POST['import_date'] ?? '');

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Số lượng nhập phải lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (!is_numeric($importPrice) || (float) $importPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Giá nhập phải lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($importDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $importDate)) {
        echo json_encode(['success' => false, 'message' => 'Ngày nhập không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $importPrice = (float) $importPrice;

    try {
        $pdo->beginTransaction();

        // Khóa dòng sản phẩm để tránh 2 người nhập kho cùng lúc đụng nhau
        $prodStmt = $pdo->prepare("SELECT product_id, sale_price FROM products WHERE product_id = ? FOR UPDATE");
        $prodStmt->execute([$productId]);
        $product = $prodStmt->fetch();

        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại.');
        }

        // Giữ đúng ràng buộc "giá bán > giá nhập" đã có ở bảng products
        if ((float) $product['sale_price'] <= $importPrice) {
            throw new Exception('Giá nhập phải nhỏ hơn giá bán hiện tại (' . formatCurrency((float) $product['sale_price']) . ').');
        }

        // Tạo lô với mã tạm, sẽ cập nhật lại sau khi có batch_id thật
        $tempCode = 'TMP' . uniqid();
        $insertBatch = $pdo->prepare("
            INSERT INTO product_batches (batch_code, product_id, import_price, quantity_imported, quantity_remaining, import_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertBatch->execute([$tempCode, $productId, $importPrice, $quantity, $quantity, $importDate]);
        $batchId = (int) $pdo->lastInsertId();

        $batchCode = 'LO' . str_pad((string) $batchId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE product_batches SET batch_code = ? WHERE batch_id = ?")
            ->execute([$batchCode, $batchId]);

        // Đồng bộ lại tồn kho tổng + giá nhập gần nhất trên bảng products
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, import_price = ? WHERE product_id = ?")
            ->execute([$quantity, $importPrice, $productId]);

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'batch_code' => $batchCode,
            'message'    => "Nhập kho thành công. Đã tạo lô $batchCode.",
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// ------------------------------------------------------------
// AJAX: Tạo sản phẩm mới + lô nhập đầu tiên (cùng 1 transaction)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_product') {
    header('Content-Type: application/json; charset=utf-8');

    $name        = trim($_POST['product_name'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $brand       = trim($_POST['brand'] ?? '');
    $importPrice = $_POST['import_price'] ?? '';
    $salePrice   = $_POST['sale_price'] ?? '';
    $quantity    = (int) ($_POST['quantity'] ?? 0);
    $importDate  = trim($_POST['import_date'] ?? '');

    // Đặc trưng AI - tùy chọn, phải điền đủ cả 4 hoặc để trống hết
    $frameType = trim($_POST['frame_type'] ?? '');
    $color     = trim($_POST['color'] ?? '');
    $material  = trim($_POST['material'] ?? '');
    $purpose   = trim($_POST['purpose'] ?? '');
    $featureFields = [$frameType, $color, $material, $purpose];
    $featureFilledCount = count(array_filter($featureFields, fn($v) => $v !== ''));

    if ($name === '' || $category === '') {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ Tên sản phẩm và Loại sản phẩm.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (!is_numeric($importPrice) || (float) $importPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Giá nhập phải lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (!is_numeric($salePrice) || (float) $salePrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Giá bán phải lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $importPrice = (float) $importPrice;
    $salePrice = (float) $salePrice;
    if ($salePrice <= $importPrice) {
        echo json_encode(['success' => false, 'message' => 'Giá bán phải lớn hơn giá nhập.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Số lượng nhập phải lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($importDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $importDate)) {
        echo json_encode(['success' => false, 'message' => 'Ngày nhập không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($featureFilledCount > 0 && $featureFilledCount < 4) {
        echo json_encode(['success' => false, 'message' => 'Đặc trưng AI: điền đủ cả 4 mục (kiểu gọng, màu, chất liệu, mục đích) hoặc để trống hết.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Tạo sản phẩm với mã tạm
        $tempProductCode = 'TMP' . uniqid();
        $insertProduct = $pdo->prepare("
            INSERT INTO products (product_code, product_name, category, brand, import_price, sale_price, stock_quantity, status)
            VALUES (?, ?, ?, ?, ?, ?, 0, 'active')
        ");
        $insertProduct->execute([$tempProductCode, $name, $category, $brand !== '' ? $brand : null, $importPrice, $salePrice]);
        $productId = (int) $pdo->lastInsertId();

        $productCode = 'SP' . str_pad((string) $productId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE products SET product_code = ? WHERE product_id = ?")
            ->execute([$productCode, $productId]);

        // 2. Tạo lô nhập đầu tiên
        $tempBatchCode = 'TMP' . uniqid();
        $insertBatch = $pdo->prepare("
            INSERT INTO product_batches (batch_code, product_id, import_price, quantity_imported, quantity_remaining, import_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertBatch->execute([$tempBatchCode, $productId, $importPrice, $quantity, $quantity, $importDate]);
        $batchId = (int) $pdo->lastInsertId();

        $batchCode = 'LO' . str_pad((string) $batchId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE product_batches SET batch_code = ? WHERE batch_id = ?")
            ->execute([$batchCode, $batchId]);

        // 3. Đồng bộ tồn kho ban đầu = số lượng vừa nhập
        $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?")
            ->execute([$quantity, $productId]);

        // 4. Gán đặc trưng AI nếu quản lý có điền (tùy chọn)
        if ($featureFilledCount === 4) {
            $pdo->prepare("
                INSERT INTO product_features (product_id, frame_type, color, material, purpose)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$productId, $frameType, $color, $material, $purpose]);
        }

        $pdo->commit();

        echo json_encode([
            'success'      => true,
            'product_code' => $productCode,
            'batch_code'   => $batchCode,
            'message'      => "Tạo sản phẩm và nhập kho thành công. Mã sản phẩm $productCode, mã lô $batchCode.",
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}
?>
<?php
$pageTitle = 'Nhập kho';
$activeNav = 'import';
require __DIR__ . '/../includes/layout/header_manager.php';
?>

        <div class="page-header">
            <h1>Nhập kho</h1>
            <a href="/doan1/manager/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>

        <div id="pageMessage"></div>

        <div class="sales-grid">

            <!-- CỘT TRÁI: tìm sản phẩm đã có -->
            <div class="sales-col">
                <div class="form-card">
                    <h2>Tìm sản phẩm đã có để nhập thêm hàng</h2>
                    <div class="search-form">
                        <input type="text" id="productKeyword" placeholder="Nhập mã, tên hoặc thương hiệu...">
                        <button type="button" class="btn-primary" id="btnSearchProduct">Tìm kiếm</button>
                    </div>
                    <div id="productResults" class="result-list"><p class="result-empty-inline">Đang tải danh sách sản phẩm...</p></div>
                </div>

                <div class="form-card">
                    <button type="button" class="btn-secondary u-full-width" id="btnToggleCreate">+ Sản phẩm này chưa có trong hệ thống — Tạo sản phẩm mới</button>

                    <div id="createProductForm" style="display:none; margin-top:16px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newName">Tên sản phẩm</label>
                                <input type="text" id="newName">
                            </div>
                            <div class="form-group">
                                <label for="newCategory">Loại sản phẩm</label>
                                <input type="text" id="newCategory" placeholder="vd: kính gọng, kính mát...">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newBrand">Thương hiệu (không bắt buộc)</label>
                                <input type="text" id="newBrand">
                            </div>
                            <div class="form-group">
                                <label for="newImportDate2">Ngày nhập</label>
                                <input type="date" id="newImportDate2">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newImportPrice">Giá nhập</label>
                                <input type="number" id="newImportPrice" min="1">
                            </div>
                            <div class="form-group">
                                <label for="newSalePrice">Giá bán</label>
                                <input type="number" id="newSalePrice" min="1">
                            </div>
                            <div class="form-group">
                                <label for="newQuantity">Số lượng nhập</label>
                                <input type="number" id="newQuantity" min="1">
                            </div>
                        </div>

                        <p class="u-hint-text" style="margin:12px 0 8px;">Đặc trưng cho AI đề xuất (không bắt buộc — điền đủ 4 mục nếu muốn sản phẩm này xuất hiện trong công cụ AI đề xuất):</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newFrameType">Kiểu gọng</label>
                                <input type="text" id="newFrameType" placeholder="vd: gọng nguyên khung, nửa khung, không gọng">
                            </div>
                            <div class="form-group">
                                <label for="newColor">Màu sắc</label>
                                <input type="text" id="newColor" placeholder="vd: đen...">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newMaterial">Chất liệu</label>
                                <input type="text" id="newMaterial" placeholder="vd: titan, nhựa, nhựa dẻo...">
                            </div>
                            <div class="form-group">
                                <label for="newPurpose">Mục đích sử dụng</label>
                                <input type="text" id="newPurpose" placeholder="vd: thời trang, thể thao, công sở...">
                            </div>
                        </div>

                        <button type="button" class="btn-primary" id="btnCreateProduct" style="margin-top:var(--space-2);">Tạo sản phẩm &amp; nhập kho</button>
                        <div id="createProductMessage"></div>
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI: nhập thêm lô cho sản phẩm đã chọn -->
            <div class="sales-col is-invoice">
                <div class="form-card">
                    <h2>Nhập thêm lô cho sản phẩm đã chọn</h2>
                    <div id="selectedProductBox" class="selected-box" style="display:none;"></div>
                    <div id="noSelectionNote" class="result-empty-inline">Chọn 1 sản phẩm ở cột bên trái để nhập thêm lô hàng.</div>

                    <div id="addBatchForm" style="display:none;">
                        <div class="form-group">
                            <label for="batchQuantity">Số lượng nhập</label>
                            <input type="number" id="batchQuantity" min="1">
                        </div>
                        <div class="form-group">
                            <label for="batchImportPrice">Giá nhập</label>
                            <input type="number" id="batchImportPrice" min="1">
                        </div>
                        <div class="form-group">
                            <label for="batchImportDate">Ngày nhập</label>
                            <input type="date" id="batchImportDate">
                        </div>
                        <button type="button" class="btn-primary u-full-width" id="btnAddBatch">Xác nhận nhập kho</button>
                        <div id="addBatchMessage"></div>
                    </div>
                </div>
            </div>

        </div>
    <script>
    (function () {
        var selectedProduct = null;

        function todayStr() {
            var d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        document.getElementById('batchImportDate').value = todayStr();
        document.getElementById('newImportDate2').value = todayStr();

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
            return fetch('import.php', {
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

        // ---------------- TÌM SẢN PHẨM ----------------
        function searchProducts() {
            var keyword = document.getElementById('productKeyword').value.trim();
            var container = document.getElementById('productResults');
            container.innerHTML = '<p class="result-loading">Đang tìm...</p>';

            postAction('search_product', { keyword: keyword }).then(function (res) {
                container.innerHTML = '';
                if (!res.success || res.products.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'empty-state';
                    empty.textContent = 'Không tìm thấy sản phẩm phù hợp. Nếu là sản phẩm mới, dùng nút "Tạo sản phẩm mới" bên dưới.';
                    container.appendChild(empty);
                    return;
                }
                res.products.forEach(function (p) {
                    var item = document.createElement('div');
                    item.className = 'result-item';

                    var info = document.createElement('div');
                    info.className = 'result-info';
                    var title = document.createElement('div');
                    title.className = 'result-title';
                    title.textContent = p.product_code + ' - ' + p.product_name;
                    var meta = document.createElement('div');
                    meta.className = 'result-meta';
                    meta.textContent = (p.category || '') + ' | Tồn hiện tại: ' + p.stock_quantity + ' | Giá nhập gần nhất: ' + formatCurrency(p.import_price);
                    info.appendChild(title);
                    info.appendChild(meta);

                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn-primary';
                    btn.textContent = 'Chọn';
                    btn.addEventListener('click', function () { selectProduct(p); });

                    item.appendChild(info);
                    item.appendChild(btn);
                    container.appendChild(item);
                });
            });
        }
        document.getElementById('btnSearchProduct').addEventListener('click', searchProducts);
        document.getElementById('productKeyword').addEventListener('keyup', function (e) { if (e.key === 'Enter') searchProducts(); });

        // Hiển thị danh sách sản phẩm ngay khi mở trang, không cần gõ tìm trước
        searchProducts();

        function selectProduct(p) {
            selectedProduct = p;
            var box = document.getElementById('selectedProductBox');
            box.style.display = 'block';
            box.textContent = 'Đã chọn: ' + p.product_code + ' - ' + p.product_name + ' (đang tồn ' + p.stock_quantity + ')';
            document.getElementById('noSelectionNote').style.display = 'none';
            document.getElementById('addBatchForm').style.display = 'block';
            document.getElementById('batchImportPrice').value = p.import_price;
        }

        document.getElementById('btnAddBatch').addEventListener('click', function () {
            if (!selectedProduct) return;
            var quantity = document.getElementById('batchQuantity').value;
            var importPrice = document.getElementById('batchImportPrice').value;
            var importDate = document.getElementById('batchImportDate').value;

            postAction('add_batch', {
                product_id: selectedProduct.product_id,
                quantity: quantity,
                import_price: importPrice,
                import_date: importDate
            }).then(function (res) {
                showMessage('addBatchMessage', res.message, !res.success);
                if (res.success) {
                    document.getElementById('batchQuantity').value = '';
                    searchProducts();
                }
            });
        });

        // ---------------- TẠO SẢN PHẨM MỚI ----------------
        document.getElementById('btnToggleCreate').addEventListener('click', function () {
            var form = document.getElementById('createProductForm');
            form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
        });

        document.getElementById('btnCreateProduct').addEventListener('click', function () {
            postAction('create_product', {
                product_name: document.getElementById('newName').value,
                category: document.getElementById('newCategory').value,
                brand: document.getElementById('newBrand').value,
                import_price: document.getElementById('newImportPrice').value,
                sale_price: document.getElementById('newSalePrice').value,
                quantity: document.getElementById('newQuantity').value,
                import_date: document.getElementById('newImportDate2').value,
                frame_type: document.getElementById('newFrameType').value,
                color: document.getElementById('newColor').value,
                material: document.getElementById('newMaterial').value,
                purpose: document.getElementById('newPurpose').value
            }).then(function (res) {
                showMessage('createProductMessage', res.message, !res.success);
                if (res.success) {
                    ['newName','newCategory','newBrand','newImportPrice','newSalePrice','newQuantity','newFrameType','newColor','newMaterial','newPurpose'].forEach(function (id) {
                        document.getElementById(id).value = '';
                    });
                    document.getElementById('newImportDate2').value = todayStr();
                }
            });
        });
    })();
    </script>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
