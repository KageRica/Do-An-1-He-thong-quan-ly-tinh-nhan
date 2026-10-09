<?php
// ============================================================
// includes/product_helper.php
// Các hàm hỗ trợ nhỏ liên quan đến hiển thị thông tin sản phẩm.
// Được tách riêng để employee/products.php và
// employee/product-detail.php dùng chung, tránh lặp code.
// employee/sales.php cũng require file này để dùng getStockStatus().
// File này KHÔNG tự require database.php, chỉ chứa các hàm
// xử lý dữ liệu thuần túy.
// ============================================================

/**
 * Xác định nhãn trạng thái tồn kho dựa trên số lượng còn lại.
 * Quy tắc:
 *   > 10  : Còn hàng
 *   1-10  : Sắp hết
 *   0     : Hết hàng
 * Không lưu trạng thái này vào database, chỉ tính khi hiển thị.
 *
 * @param int $quantity Tổng số lượng tồn kho (SUM từ product_batches)
 * @return array{label: string, css_class: string}
 */
function getStockStatus(int $quantity): array
{
    if ($quantity <= 0) {
        return ['label' => 'Hết hàng', 'css_class' => 'stock-out'];
    } elseif ($quantity <= 10) {
        return ['label' => 'Sắp hết', 'css_class' => 'stock-low'];
    } else {
        return ['label' => 'Còn hàng', 'css_class' => 'stock-ok'];
    }
}

/**
 * Định dạng số tiền theo kiểu Việt Nam, ví dụ: 550000 -> "550.000 đ"
 *
 * @param float $amount
 * @return string
 */
function formatCurrency(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' đ';
}

/**
 * ------------------------------------------------------------
 * Mapping hiển thị tiếng Việt cho các đặc trưng AI KNN
 * (bảng product_features: frame_type, color, material, purpose).
 *
 * CHỈ dùng cho HIỂN THỊ. Giá trị lưu trong database và giá trị
 * gửi qua form/GET (AI KNN) vẫn giữ nguyên dạng code gốc
 * (vd: "khong_gong", "hong_pastel"...) - không đổi ở đây.
 *
 * Dùng chung cho employee/product-detail.php và
 * employee/ai-recommend.php để tránh 2 bộ mapping khác nhau.
 * ------------------------------------------------------------
 */
const FEATURE_LABELS = [
    'frame_type' => [
        'full_rim'   => 'Gọng nguyên khung',
        'half_rim'   => 'Gọng nửa khung',
        'khong_gong' => 'Không gọng',
    ],
    'color' => [
        'den'          => 'Đen',
        'xanh_navy'    => 'Xanh navy',
        'trong_suot'   => 'Trong suốt',
        'hong_pastel'  => 'Hồng pastel',
        'nau_tra'      => 'Nâu trà',
        'xam_doi_mau'  => 'Xám đổi màu',
        'bac'          => 'Bạc',
        'xam_dam'      => 'Xám đậm',
    ],
    'material' => [
        'titan'             => 'Titan',
        'nhua'              => 'Nhựa',
        'nhua_deo'          => 'Nhựa dẻo',
        'nhua_chiet_quang'  => 'Nhựa chiết quang',
        'kim_loai'          => 'Kim loại',
        'nhua_quang_hoc'    => 'Nhựa quang học',
        'nhua_phan_cuc'     => 'Nhựa phân cực',
    ],
    'purpose' => [
        'thoi_trang'         => 'Thời trang',
        'the_thao'           => 'Thể thao',
        'lam_viec_may_tinh'  => 'Làm việc máy tính',
        'lai_xe'             => 'Lái xe',
        'cong_so'            => 'Công sở',
    ],
];

/**
 * Chuyển giá trị code của một đặc trưng (frame_type/color/material/purpose)
 * sang nhãn tiếng Việt để hiển thị. Nếu giá trị chưa có trong dictionary
 * (dữ liệu mới chưa kịp cập nhật mapping), rơi về dạng "Title Case" từ
 * chính giá trị gốc thay vì hiển thị rỗng hoặc lỗi.
 *
 * @param string $featureType 'frame_type' | 'color' | 'material' | 'purpose'
 * @param string|null $value Giá trị code lấy từ product_features
 * @return string
 */
function formatFeatureLabel(string $featureType, ?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (isset(FEATURE_LABELS[$featureType][$value])) {
        return FEATURE_LABELS[$featureType][$value];
    }

    // Fallback an toàn cho giá trị chưa có trong dictionary
    return mb_convert_case(str_replace('_', ' ', $value), MB_CASE_TITLE, 'UTF-8');
}


/**
 * Nhãn hiển thị cho phương thức thanh toán.
 * Dùng chung: employee/invoices.php, employee/invoice.php, manager/reports.php.
 */
function formatPaymentMethod(string $method): string
{
    return $method === 'transfer' ? 'Chuyển khoản' : 'Tiền mặt';
}

/**
 * Nhãn + class CSS hiển thị cho trạng thái hóa đơn.
 * Tái dùng class .stock-ok / .stock-out có sẵn trong style.css.
 */
function formatInvoiceStatus(string $status): array
{
    if ($status === 'cancelled') {
        return ['label' => 'Đã hủy', 'css_class' => 'stock-out'];
    }
    return ['label' => 'Hoàn tất', 'css_class' => 'stock-ok'];
}

/**
 * Định dạng mã khách hàng để HIỂN THỊ, dựa trên customer_id.
 * CHỈ là format ở tầng PHP, KHÔNG lưu vào database.
 * Ví dụ: customer_id = 1 -> "KH0001"
 * Dùng chung: employee/sales.php, employee/customers.php.
 */
function formatCustomerCode(int $customerId): string
{
    return 'KH' . str_pad((string) $customerId, 4, '0', STR_PAD_LEFT);
}

/**
 * Kiểm tra định dạng số điện thoại hợp lệ (9-15 chữ số).
 * Dùng chung: employee/sales.php, employee/customers.php.
 */
function isValidPhone(string $phone): bool
{
    return (bool) preg_match('/^[0-9]{9,15}$/', $phone);
}
