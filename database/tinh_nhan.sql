-- ============================================================
-- DATABASE: tinh_nhan
-- Đồ án 1 - Website quản lý bán hàng cửa hàng mắt kính Tinh Nhãn
-- Database DÙNG CHUNG cho 2 phần: Nhân viên và Quản lý
-- Bước 1: CHỈ tạo cấu trúc bảng + dữ liệu mẫu. Không chứa logic PHP.
-- Charset: utf8mb4 | Engine: InnoDB (hỗ trợ FOREIGN KEY)
-- ============================================================

CREATE DATABASE IF NOT EXISTS tinh_nhan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tinh_nhan;

-- ============================================================
-- BẢNG 1: users
-- Tài khoản đăng nhập dùng chung cho Nhân viên và Quản lý.
-- Không có chức năng đăng ký công khai -> tài khoản do Quản lý tạo.
-- Mật khẩu PHẢI được hash bằng password_hash() ở PHP.
-- ============================================================
CREATE TABLE users (
    user_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL,
    password    VARCHAR(255) NOT NULL,          -- lưu chuỗi hash từ password_hash()
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('employee', 'manager') NOT NULL,
    status      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BẢNG 2: products
-- Thông tin gốc của sản phẩm (kính gọng, kính mát, tròng kính...).
-- stock_quantity = tổng quantity_remaining của các lô trong product_batches
-- (được đồng bộ ở tầng PHP mỗi khi nhập/bán hàng, để tra cứu nhanh
-- không phải JOIN với product_batches mỗi lần tìm kiếm sản phẩm).
-- ============================================================
CREATE TABLE products (
    product_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code   VARCHAR(20)  NOT NULL,        -- mã sản phẩm hiển thị, vd: SP0001
    product_name   VARCHAR(150) NOT NULL,
    category       VARCHAR(50)  NOT NULL,        -- loại kính: kính gọng / kính mát / tròng kính đổi màu / ...
    brand          VARCHAR(50)  DEFAULT NULL,
    import_price   DECIMAL(12,2) NOT NULL,       -- giá nhập gần nhất (tham khảo nhanh, giá thật theo từng lô)
    sale_price     DECIMAL(12,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,       -- tổng tồn kho hiện tại (đồng bộ từ product_batches)
    status         ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_products_code (product_code),
    KEY idx_products_name (product_name),
    KEY idx_products_category (category),
    CONSTRAINT chk_products_price CHECK (sale_price > import_price AND import_price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BẢNG 3: product_batches
-- Mỗi lần nhập hàng tạo 1 lô riêng biệt -> phục vụ FIFO khi bán
-- và phát hiện hàng tồn lâu.
-- ============================================================
CREATE TABLE product_batches (
    batch_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_code         VARCHAR(20) NOT NULL,          -- mã lô hiển thị, vd: LO0001
    product_id         INT UNSIGNED NOT NULL,
    import_price       DECIMAL(12,2) NOT NULL,
    quantity_imported  INT NOT NULL,
    quantity_remaining INT NOT NULL,
    import_date        DATE NOT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_batches_code (batch_code),
    KEY idx_batches_product_date (product_id, import_date),   -- phục vụ truy vấn FIFO nhanh
    CONSTRAINT fk_batches_product FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_batches_qty CHECK (
        quantity_imported > 0
        AND quantity_remaining >= 0
        AND quantity_remaining <= quantity_imported
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BẢNG 4: customers
-- Khách hàng do Nhân viên tạo trong lúc bán hàng.
-- ============================================================
CREATE TABLE customers (
    customer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    phone       VARCHAR(15)  NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BẢNG 5: invoices
-- Hóa đơn bán hàng do Nhân viên lập.
-- ============================================================
CREATE TABLE invoices (
    invoice_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_code   VARCHAR(20) NOT NULL,               -- mã hóa đơn hiển thị, vd: HD0001
    customer_id    INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL,               -- nhân viên lập hóa đơn
    total_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash', 'transfer') NOT NULL,
    status         ENUM('completed', 'cancelled') NOT NULL DEFAULT 'completed',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_invoices_code (invoice_code),
    KEY idx_invoices_created_at (created_at),           -- phục vụ báo cáo doanh thu theo khoảng thời gian
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id)
        REFERENCES customers(customer_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BẢNG 6: invoice_details
-- Chi tiết từng dòng sản phẩm trong hóa đơn.
-- Lưu batch_id để biết chính xác đã xuất từ lô nào (giá vốn thật
-- theo FIFO, phục vụ tính lợi nhuận ở phần báo cáo của Quản lý).
-- ============================================================
CREATE TABLE invoice_details (
    detail_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    batch_id    INT UNSIGNED NOT NULL,
    quantity    INT NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,
    subtotal    DECIMAL(12,2) NOT NULL,
    KEY idx_details_invoice (invoice_id),
    KEY idx_details_product (product_id),
    CONSTRAINT fk_details_invoice FOREIGN KEY (invoice_id)
        REFERENCES invoices(invoice_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_details_product FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_details_batch FOREIGN KEY (batch_id)
        REFERENCES product_batches(batch_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_details_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BẢNG 7: product_features
-- Dành riêng cho module AI KNN (employee/ai-recommend.php ở bước sau).
-- Quan hệ 1-1 với products.
-- ============================================================
CREATE TABLE product_features (
    feature_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    frame_type  VARCHAR(50) NOT NULL,   -- kiểu gọng: full_rim / half_rim / khong_gong ...
    color       VARCHAR(50) NOT NULL,   -- màu sắc
    material    VARCHAR(50) NOT NULL,   -- chất liệu
    purpose     VARCHAR(50) NOT NULL,   -- mục đích sử dụng: thời trang / thể thao / lái xe / công sở ...
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_features_product (product_id),
    CONSTRAINT fk_features_product FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- DỮ LIỆU MẪU
-- ============================================================

-- ---------- 1. users ----------
-- Tài khoản manager  : username = admin      | mật khẩu gốc = manager123
-- Tài khoản employee : username = nhanvien1  | mật khẩu gốc = nhanvien123
INSERT INTO users (username, password, full_name, role, status) VALUES
('admin',     '$2b$10$yoM1rnnN..jjXU0Y7aJc9OODMa7Hu6iaUuQOhtfnLahw18YmmN0Hm', 'Nguyễn Nhất Thành', 'manager',  'active'),
('nhanvien1', '$2b$10$tV518pPAmMCEbbpTjf1C0evAUVkWNGOLELZ0wx5hEKS3YZKYfJo9e', 'Nguyễn Hoàng Phát', 'employee', 'active');

-- ---------- 2. products ----------
INSERT INTO products (product_code, product_name, category, brand, import_price, sale_price, stock_quantity, status) VALUES
('SP0001', 'Gọng kính Titan Classic',                 'kính gọng',                          'RayBrand',  300000, 550000, 30, 'active'),
('SP0002', 'Kính mát Polarized Sport',                'kính mát',                           'SunPro',    250000, 480000, 22, 'active'),
('SP0003', 'Tròng kính chống ánh sáng xanh Slim',      'tròng kính chống ánh sáng xanh',     'OptiLens',  200000, 420000, 28, 'active'),
('SP0004', 'Gọng kính nhựa dẻo Trendy',                'kính gọng',                          'Fashionex', 150000, 320000, 20, 'active'),
('SP0005', 'Kính mát thời trang Vintage',              'kính mát',                           'SunPro',    220000, 450000, 18, 'active'),
('SP0006', 'Tròng kính đổi màu Photochromic',          'tròng kính đổi màu',                 'OptiLens',  350000, 650000, 11, 'active'),
('SP0007', 'Gọng kính kim loại Business',              'kính gọng',                          'RayBrand',  400000, 750000, 10, 'active'),
('SP0008', 'Tròng kính phân cực Driver',               'tròng kính phân cực',                'OptiLens',  280000, 520000, 22, 'active');

-- ---------- 3. product_batches ----------
INSERT INTO product_batches (batch_code, product_id, import_price, quantity_imported, quantity_remaining, import_date) VALUES
('LO0001', 1, 300000, 20, 15, '2026-03-15'),  -- SP0001 lô cũ, đã bán 5 (FIFO ưu tiên lô này trước)
('LO0002', 1, 310000, 15, 15, '2026-07-20'),  -- SP0001 lô mới
('LO0003', 2, 250000, 25, 22, '2026-06-01'),  -- SP0002, đã bán 3
('LO0004', 3, 200000, 30, 28, '2026-07-01'),  -- SP0003, đã bán 2
('LO0005', 4, 150000, 20, 20, '2026-05-10'),  -- SP0004
('LO0006', 5, 220000, 18, 18, '2026-06-15'),  -- SP0005
('LO0007', 6, 350000, 12, 11, '2026-07-25'),  -- SP0006, đã bán 1
('LO0008', 7, 400000, 10, 10, '2026-08-01'),  -- SP0007
('LO0009', 8, 280000, 22, 22, '2026-06-20');  -- SP0008

-- ---------- 4. customers ----------
INSERT INTO customers (full_name, phone) VALUES
('Trần Thị Mai',    '0901234567'),
('Lê Văn Hùng',      '0912345678'),
('Phạm Thị Ngọc',    '0987654321');

-- ---------- 5. product_features ----------
INSERT INTO product_features (product_id, frame_type, color, material, purpose) VALUES
(1, 'full_rim',   'den',          'titan',             'thoi_trang'),
(2, 'full_rim',   'xanh_navy',    'nhua',              'the_thao'),
(3, 'khong_gong', 'trong_suot',   'nhua_chiet_quang',  'lam_viec_may_tinh'),
(4, 'full_rim',   'hong_pastel',  'nhua_deo',          'thoi_trang'),
(5, 'half_rim',   'nau_tra',      'kim_loai',          'thoi_trang'),
(6, 'khong_gong', 'xam_doi_mau',  'nhua_quang_hoc',    'lai_xe'),
(7, 'full_rim',   'bac',          'kim_loai',          'cong_so'),
(8, 'khong_gong', 'xam_dam',      'nhua_phan_cuc',     'lai_xe');

-- ---------- 6. invoices ----------
INSERT INTO invoices (invoice_code, customer_id, user_id, total_amount, payment_method, status, created_at) VALUES
('HD0001', 1, 2, 3590000, 'cash',     'completed', '2026-08-10 09:30:00'),
('HD0002', 2, 2, 2090000, 'transfer', 'completed', '2026-08-12 14:15:00');

-- ---------- 7. invoice_details ----------
INSERT INTO invoice_details (invoice_id, product_id, batch_id, quantity, unit_price, subtotal) VALUES
(1, 1, 1, 5, 550000, 2750000),
(1, 3, 4, 2, 420000, 840000);

INSERT INTO invoice_details (invoice_id, product_id, batch_id, quantity, unit_price, subtotal) VALUES
(2, 2, 3, 3, 480000, 1440000),
(2, 6, 7, 1, 650000, 650000);
