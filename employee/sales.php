<?php
// ============================================================
// employee/sales.php
// Bước 7: Nhân viên - Bán hàng / Lập hóa đơn.
// File này xử lý:
//   - Trang giao diện bán hàng (GET thông thường).
//   - Các hành động AJAX (POST action=...):
//       + search_product : tìm sản phẩm đang kinh doanh
//       + search_customer: tìm khách hàng có sẵn
//       + add_customer   : thêm khách hàng mới nhanh trong lúc bán
//       + checkout       : xác nhận thanh toán, tạo hóa đơn (transaction + FIFO)
// ============================================================

require_once __DIR__ . '/../includes/employee_guard.php';
require_once __DIR__ . '/../includes/product_helper.php';
require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';

// ------------------------------------------------------------
// AJAX: Tìm kiếm sản phẩm đang kinh doanh (theo mã/tên/thương hiệu)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'search_product') {
    header('Content-Type: application/json; charset=utf-8');

    $keyword = trim($_POST['keyword'] ?? '');

    $whereSql = "WHERE p.status = 'active'";
    $params = [];

    if ($keyword !== '') {
        $whereSql .= " AND (p.product_code LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?)";
        $likeKeyword = '%' . $keyword . '%';
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
    }

    $sql = "
        SELECT
            p.product_id,
            p.product_code,
            p.product_name,
            p.category,
            p.brand,
            p.sale_price,
            COALESCE(SUM(b.quantity_remaining), 0) AS stock_quantity
        FROM products p
        LEFT JOIN product_batches b ON b.product_id = p.product_id
        $whereSql
        GROUP BY p.product_id, p.product_code, p.product_name, p.category, p.brand, p.sale_price
        ORDER BY p.product_name ASC
        LIMIT 20
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
            'sale_price'     => (float) $row['sale_price'],
            'stock_quantity' => (int) $row['stock_quantity'],
            'stock_label'    => $stock['label'],
            'stock_class'    => $stock['css_class'],
        ];
    }

    echo json_encode(['success' => true, 'products' => $result], JSON_UNESCAPED_UNICODE);
    exit();
}

// ------------------------------------------------------------
// AJAX: Tìm kiếm khách hàng có sẵn (theo tên/SĐT)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'search_customer') {
    header('Content-Type: application/json; charset=utf-8');

    $keyword = trim($_POST['keyword'] ?? '');

    $whereSql = '';
    $params = [];

    if ($keyword !== '') {
        $whereSql = 'WHERE full_name LIKE ? OR phone LIKE ?';
        $likeKeyword = '%' . $keyword . '%';
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
    }

    $sql = "
        SELECT customer_id, full_name, phone
        FROM customers
        $whereSql
        ORDER BY created_at DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'customer_id'   => (int) $row['customer_id'],
            'customer_code' => formatCustomerCode((int) $row['customer_id']),
            'full_name'     => $row['full_name'],
            'phone'         => $row['phone'],
        ];
    }

    echo json_encode(['success' => true, 'customers' => $result], JSON_UNESCAPED_UNICODE);
    exit();
}

// ------------------------------------------------------------
// AJAX: Thêm khách hàng mới nhanh trong lúc bán hàng
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_customer') {
    header('Content-Type: application/json; charset=utf-8');

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($fullName === '' || $phone === '') {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ Họ tên và Số điện thoại.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!isValidPhone($phone)) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại không đúng định dạng (chỉ gồm chữ số, 9-15 ký tự).'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $checkStmt = $pdo->prepare("SELECT customer_id FROM customers WHERE phone = ?");
    $checkStmt->execute([$phone]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại này đã tồn tại trong hệ thống.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $insertStmt = $pdo->prepare("INSERT INTO customers (full_name, phone) VALUES (?, ?)");
        $insertStmt->execute([$fullName, $phone]);
        $newId = (int) $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'customer' => [
                'customer_id'   => $newId,
                'customer_code' => formatCustomerCode($newId),
                'full_name'     => $fullName,
                'phone'         => $phone,
            ],
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại này đã tồn tại trong hệ thống.'], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// ------------------------------------------------------------
// AJAX: Xác nhận thanh toán - tạo hóa đơn (TRANSACTION + FIFO)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'checkout') {
    header('Content-Type: application/json; charset=utf-8');

    // --- 1. Kiểm tra nhân viên đăng nhập (guard đã đảm bảo, lấy user_id từ session) ---
    $employeeId = (int) ($_SESSION['user_id'] ?? 0);
    if ($employeeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- 2. Đọc và kiểm tra dữ liệu đầu vào cơ bản ---
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $itemsRaw = json_decode($_POST['items'] ?? '[]', true);

    if ($customerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn khách hàng.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
        echo json_encode(['success' => false, 'message' => 'Phương thức thanh toán không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!is_array($itemsRaw) || empty($itemsRaw)) {
        echo json_encode(['success' => false, 'message' => 'Hóa đơn chưa có sản phẩm nào.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Gộp số lượng nếu client gửi trùng product_id, ép kiểu int an toàn
    $itemsByProduct = [];
    foreach ($itemsRaw as $item) {
        $pid = (int) ($item['product_id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 0);

        if ($pid <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu sản phẩm trong hóa đơn không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $itemsByProduct[$pid] = ($itemsByProduct[$pid] ?? 0) + $qty;
    }

    // --- 3. Thực hiện toàn bộ trong TRANSACTION ---
    try {
        $pdo->beginTransaction();

        // Kiểm tra khách hàng tồn tại
        $custStmt = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
        $custStmt->execute([$customerId]);
        if (!$custStmt->fetch()) {
            throw new Exception('Khách hàng không tồn tại trong hệ thống.');
        }

        // Tạo hóa đơn tạm với invoice_code tạm thời (sẽ cập nhật lại sau khi có invoice_id)
        $tempCode = 'TMP' . uniqid();
        $insertInvoiceStmt = $pdo->prepare("
            INSERT INTO invoices (invoice_code, customer_id, user_id, total_amount, payment_method, status)
            VALUES (?, ?, ?, 0, ?, 'completed')
        ");
        $insertInvoiceStmt->execute([$tempCode, $customerId, $employeeId, $paymentMethod]);
        $invoiceId = (int) $pdo->lastInsertId();

        $totalAmount = 0.0;

        // Xử lý từng sản phẩm trong hóa đơn
        foreach ($itemsByProduct as $productId => $qtyNeeded) {
            // Khóa dòng sản phẩm để tránh race-condition, đồng thời kiểm tra active
            $prodStmt = $pdo->prepare("SELECT product_id, product_name, sale_price, status FROM products WHERE product_id = ? FOR UPDATE");
            $prodStmt->execute([$productId]);
            $productRow = $prodStmt->fetch();

            if (!$productRow) {
                throw new Exception("Sản phẩm mã #$productId không tồn tại.");
            }

            if ($productRow['status'] !== 'active') {
                throw new Exception('Sản phẩm "' . $productRow['product_name'] . '" hiện không còn kinh doanh.');
            }

            // Lấy các lô còn hàng theo FIFO (import_date cũ nhất trước), khóa để tránh bán trùng
            $batchStmt = $pdo->prepare("
                SELECT batch_id, quantity_remaining
                FROM product_batches
                WHERE product_id = ? AND quantity_remaining > 0
                ORDER BY import_date ASC, batch_id ASC
                FOR UPDATE
            ");
            $batchStmt->execute([$productId]);
            $batches = $batchStmt->fetchAll();

            $totalAvailable = 0;
            foreach ($batches as $b) {
                $totalAvailable += (int) $b['quantity_remaining'];
            }

            if ($totalAvailable < $qtyNeeded) {
                throw new Exception('Sản phẩm "' . $productRow['product_name'] . '" không đủ tồn kho. Chỉ còn ' . $totalAvailable . ' sản phẩm.');
            }

            $unitPrice = (float) $productRow['sale_price'];
            $remainingToTake = $qtyNeeded;

            // Trừ tồn kho theo FIFO, có thể phải lấy từ nhiều lô
            foreach ($batches as $batch) {
                if ($remainingToTake <= 0) {
                    break;
                }

                $batchId = (int) $batch['batch_id'];
                $available = (int) $batch['quantity_remaining'];
                $take = min($available, $remainingToTake);

                $updateBatchStmt = $pdo->prepare("
                    UPDATE product_batches
                    SET quantity_remaining = quantity_remaining - ?
                    WHERE batch_id = ? AND quantity_remaining >= ?
                ");
                $updateBatchStmt->execute([$take, $batchId, $take]);

                if ($updateBatchStmt->rowCount() === 0) {
                    // Phòng trường hợp dữ liệu bị thay đổi giữa lúc SELECT và UPDATE
                    throw new Exception('Lỗi cập nhật tồn kho cho sản phẩm "' . $productRow['product_name'] . '". Vui lòng thử lại.');
                }

                $subtotal = $take * $unitPrice;

                $insertDetailStmt = $pdo->prepare("
                    INSERT INTO invoice_details (invoice_id, product_id, batch_id, quantity, unit_price, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertDetailStmt->execute([$invoiceId, $productId, $batchId, $take, $unitPrice, $subtotal]);

                $totalAmount += $subtotal;
                $remainingToTake -= $take;
            }

            if ($remainingToTake > 0) {
                // Không nên xảy ra vì đã kiểm tra $totalAvailable ở trên, chỉ là lớp bảo vệ thêm.
                throw new Exception('Không thể trừ đủ tồn kho cho sản phẩm "' . $productRow['product_name'] . '".');
            }
        }

        // Cập nhật lại mã hóa đơn thật (dựa trên invoice_id) và tổng tiền chính xác
        $invoiceCode = 'HD' . str_pad((string) $invoiceId, 4, '0', STR_PAD_LEFT);
        $updateInvoiceStmt = $pdo->prepare("UPDATE invoices SET invoice_code = ?, total_amount = ? WHERE invoice_id = ?");
        $updateInvoiceStmt->execute([$invoiceCode, $totalAmount, $invoiceId]);

        $pdo->commit();

        echo json_encode([
            'success'       => true,
            'invoice_id'    => $invoiceId,
            'invoice_code'  => $invoiceCode,
            'total_amount'  => $totalAmount,
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
?>
<?php
$pageTitle = 'Bán hàng';
$activeNav = 'sales';
require __DIR__ . '/../includes/layout/header_employee.php';
?>

        <div class="page-header">
            <h1>Bán hàng</h1>
            <a href="/doan1/employee/index.php" class="btn-secondary">← Quay về Dashboard</a>
        </div>

        <div id="checkoutMessage"></div>

        <div class="sales-grid">

            <!-- CỘT TRÁI: Tìm sản phẩm -->
            <div class="sales-col">
                <div class="form-card">
                    <h2>Tìm sản phẩm</h2>
                    <div class="search-form">
                        <input type="text" id="productKeyword" placeholder="Nhập mã, tên hoặc thương hiệu...">
                        <button type="button" class="btn-primary" id="btnSearchProduct">Tìm kiếm</button>
                    </div>
                    <div id="productResults" class="result-list"><p class="result-empty-inline">Đang tải danh sách sản phẩm...</p></div>
                </div>
            </div>

            <!-- CỘT PHẢI: Hóa đơn hiện tại -->
            <div class="sales-col is-invoice">

                <!-- Khách hàng -->
                <div class="form-card">
                    <h2>Khách hàng</h2>
                    <div id="selectedCustomerBox" class="selected-box" style="display:none;"></div>
                    <div class="search-form">
                        <input type="text" id="customerKeyword" placeholder="Tìm theo họ tên hoặc SĐT...">
                        <button type="button" class="btn-primary" id="btnSearchCustomer">Tìm</button>
                        <button type="button" class="btn-secondary" id="btnToggleAddCustomer">+ Khách mới</button>
                    </div>
                    <div id="customerResults" class="result-list"></div>
                    <div id="addCustomerForm" class="add-customer-form" style="display:none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newCustomerName">Họ tên</label>
                                <input type="text" id="newCustomerName">
                            </div>
                            <div class="form-group">
                                <label for="newCustomerPhone">Số điện thoại</label>
                                <input type="text" id="newCustomerPhone">
                            </div>
                        </div>
                        <button type="button" class="btn-primary" id="btnAddCustomer">Lưu khách hàng</button>
                        <div id="addCustomerMessage"></div>
                    </div>
                </div>

                <!-- Giỏ hàng -->
                <div class="form-card">
                    <h2>Hóa đơn hiện tại</h2>
                    <table class="product-table" id="cartTable">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Đơn giá</th>
                                <th>SL</th>
                                <th>Thành tiền</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="cartEmptyRow">
                                <td colspan="5" style="text-align:center; color:#64748b;">Chưa có sản phẩm nào.</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="total-box">
                        Tổng tiền: <span id="totalAmount">0 đ</span>
                    </div>
                    <div class="form-group">
                        <label for="paymentMethod">Phương thức thanh toán</label>
                        <select id="paymentMethod">
                            <option value="cash">Tiền mặt</option>
                            <option value="transfer">Chuyển khoản</option>
                        </select>
                    </div>
                    <button type="button" class="btn-primary" id="btnCheckout">Xác nhận thanh toán</button>
                </div>

            </div>
        </div>
    <script>
    (function () {
        var cart = [];               // { product_id, product_code, product_name, sale_price, quantity, stock_quantity }
        var selectedCustomer = null; // { customer_id, customer_code, full_name, phone }

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
            return fetch('sales.php', {
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

        function clearMessage(containerId) {
            document.getElementById(containerId).innerHTML = '';
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
                    empty.textContent = 'Không tìm thấy sản phẩm phù hợp.';
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
                    meta.textContent = (p.category || '') + ' | ' + (p.brand || '') + ' | ' + formatCurrency(p.sale_price) + ' | Tồn: ' + p.stock_quantity + ' (' + p.stock_label + ')';

                    info.appendChild(title);
                    info.appendChild(meta);

                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn-primary';
                    btn.textContent = 'Thêm';
                    btn.disabled = p.stock_quantity <= 0;
                    btn.addEventListener('click', function () {
                        addToCart(p);
                    });

                    item.appendChild(info);
                    item.appendChild(btn);
                    container.appendChild(item);
                });
            });
        }

        document.getElementById('btnSearchProduct').addEventListener('click', searchProducts);
        document.getElementById('productKeyword').addEventListener('keyup', function (e) {
            if (e.key === 'Enter') searchProducts();
        });

        // ---------------- GIỎ HÀNG ----------------
        function addToCart(product) {
            var existing = cart.find(function (item) { return item.product_id === product.product_id; });
            if (existing) {
                if (existing.quantity < product.stock_quantity) {
                    existing.quantity += 1;
                }
            } else {
                cart.push({
                    product_id: product.product_id,
                    product_code: product.product_code,
                    product_name: product.product_name,
                    sale_price: product.sale_price,
                    quantity: 1,
                    stock_quantity: product.stock_quantity
                });
            }
            renderCart();
        }

        function removeFromCart(productId) {
            cart = cart.filter(function (item) { return item.product_id !== productId; });
            renderCart();
        }

        function updateQuantity(productId, qty) {
            var item = cart.find(function (i) { return i.product_id === productId; });
            if (!item) return;
            qty = parseInt(qty, 10);
            if (isNaN(qty) || qty < 1) qty = 1;
            if (qty > item.stock_quantity) qty = item.stock_quantity;
            item.quantity = qty;
            renderCart();
        }

        function renderCart() {
            var body = document.getElementById('cartBody');
            body.innerHTML = '';

            if (cart.length === 0) {
                var emptyRow = document.createElement('tr');
                var emptyCell = document.createElement('td');
                emptyCell.colSpan = 5;
                emptyCell.style.textAlign = 'center';
                emptyCell.style.color = '#64748b';
                emptyCell.textContent = 'Chưa có sản phẩm nào.';
                emptyRow.appendChild(emptyCell);
                body.appendChild(emptyRow);
                document.getElementById('totalAmount').textContent = formatCurrency(0);
                return;
            }

            var total = 0;
            cart.forEach(function (item) {
                var lineTotal = item.quantity * item.sale_price;
                total += lineTotal;

                var row = document.createElement('tr');

                var nameCell = document.createElement('td');
                nameCell.textContent = item.product_code + ' - ' + item.product_name;

                var priceCell = document.createElement('td');
                priceCell.textContent = formatCurrency(item.sale_price);

                var qtyCell = document.createElement('td');
                var qtyInput = document.createElement('input');
                qtyInput.type = 'number';
                qtyInput.min = '1';
                qtyInput.max = String(item.stock_quantity);
                qtyInput.value = item.quantity;
                qtyInput.className = 'qty-input';
                // Ép kích thước ngay trên phần tử để ô SL không bao giờ bị co về 0 (kể cả khi CSS cũ còn trong cache)
                qtyInput.style.width = '76px';
                qtyInput.style.minWidth = '76px';
                qtyInput.style.padding = '6px 4px 6px 8px';
                qtyInput.addEventListener('change', function () {
                    updateQuantity(item.product_id, qtyInput.value);
                });
                qtyCell.appendChild(qtyInput);

                var subtotalCell = document.createElement('td');
                subtotalCell.textContent = formatCurrency(lineTotal);

                var actionCell = document.createElement('td');
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-link';
                removeBtn.textContent = 'Xóa';
                removeBtn.addEventListener('click', function () {
                    removeFromCart(item.product_id);
                });
                actionCell.appendChild(removeBtn);

                row.appendChild(nameCell);
                row.appendChild(priceCell);
                row.appendChild(qtyCell);
                row.appendChild(subtotalCell);
                row.appendChild(actionCell);

                body.appendChild(row);
            });

            document.getElementById('totalAmount').textContent = formatCurrency(total);
        }

        // ---------------- KHÁCH HÀNG ----------------
        function searchCustomers() {
            var keyword = document.getElementById('customerKeyword').value.trim();
            var container = document.getElementById('customerResults');
            container.innerHTML = '<p class="result-loading">Đang tìm...</p>';

            postAction('search_customer', { keyword: keyword }).then(function (res) {
                container.innerHTML = '';

                if (!res.success || res.customers.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'empty-state';
                    empty.textContent = 'Không tìm thấy khách hàng phù hợp.';
                    container.appendChild(empty);
                    return;
                }

                res.customers.forEach(function (c) {
                    var item = document.createElement('div');
                    item.className = 'result-item';

                    var info = document.createElement('div');
                    info.className = 'result-info';

                    var title = document.createElement('div');
                    title.className = 'result-title';
                    title.textContent = c.customer_code + ' - ' + c.full_name;

                    var meta = document.createElement('div');
                    meta.className = 'result-meta';
                    meta.textContent = c.phone;

                    info.appendChild(title);
                    info.appendChild(meta);

                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn-primary';
                    btn.textContent = 'Chọn';
                    btn.addEventListener('click', function () {
                        selectCustomer(c);
                    });

                    item.appendChild(info);
                    item.appendChild(btn);
                    container.appendChild(item);
                });
            });
        }

        function selectCustomer(customer) {
            selectedCustomer = customer;
            var box = document.getElementById('selectedCustomerBox');
            box.style.display = 'block';
            box.innerHTML = '';
            var text = document.createElement('span');
            text.textContent = 'Đã chọn: ' + customer.customer_code + ' - ' + customer.full_name + ' (' + customer.phone + ')';
            box.appendChild(text);
            document.getElementById('customerResults').innerHTML = '';
            document.getElementById('customerKeyword').value = '';
        }

        document.getElementById('btnSearchCustomer').addEventListener('click', searchCustomers);
        document.getElementById('customerKeyword').addEventListener('keyup', function (e) {
            if (e.key === 'Enter') searchCustomers();
        });

        document.getElementById('btnToggleAddCustomer').addEventListener('click', function () {
            var form = document.getElementById('addCustomerForm');
            form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
        });

        document.getElementById('btnAddCustomer').addEventListener('click', function () {
            var fullName = document.getElementById('newCustomerName').value.trim();
            var phone = document.getElementById('newCustomerPhone').value.trim();
            clearMessage('addCustomerMessage');

            postAction('add_customer', { full_name: fullName, phone: phone }).then(function (res) {
                if (res.success) {
                    selectCustomer(res.customer);
                    document.getElementById('newCustomerName').value = '';
                    document.getElementById('newCustomerPhone').value = '';
                    document.getElementById('addCustomerForm').style.display = 'none';
                } else {
                    showMessage('addCustomerMessage', res.message, true);
                }
            });
        });

        // ---------------- THANH TOÁN ----------------
        document.getElementById('btnCheckout').addEventListener('click', function () {
            clearMessage('checkoutMessage');

            if (!selectedCustomer) {
                showMessage('checkoutMessage', 'Vui lòng chọn khách hàng trước khi thanh toán.', true);
                return;
            }

            if (cart.length === 0) {
                showMessage('checkoutMessage', 'Hóa đơn chưa có sản phẩm nào.', true);
                return;
            }

            var items = cart.map(function (item) {
                return { product_id: item.product_id, quantity: item.quantity };
            });

            var paymentMethod = document.getElementById('paymentMethod').value;
            var btn = document.getElementById('btnCheckout');
            btn.disabled = true;

            postAction('checkout', {
                customer_id: selectedCustomer.customer_id,
                payment_method: paymentMethod,
                items: JSON.stringify(items)
            }).then(function (res) {
                btn.disabled = false;
                if (res.success) {
                    showMessage('checkoutMessage', 'Thanh toán thành công! Mã hóa đơn: ' + res.invoice_code + ' - Tổng tiền: ' + formatCurrency(res.total_amount), false);
                    cart = [];
                    renderCart();
                    selectedCustomer = null;
                    document.getElementById('selectedCustomerBox').style.display = 'none';
                } else {
                    showMessage('checkoutMessage', res.message, true);
                }
            }).catch(function () {
                btn.disabled = false;
                showMessage('checkoutMessage', 'Có lỗi xảy ra, vui lòng thử lại.', true);
            });
        });

        renderCart();

        // Hiển thị sẵn danh sách sản phẩm và khách hàng ngay khi mở trang,
        // không bắt nhân viên phải gõ tìm trước mới thấy được gì để chọn.
        searchProducts();
        searchCustomers();
    })();
    </script>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
