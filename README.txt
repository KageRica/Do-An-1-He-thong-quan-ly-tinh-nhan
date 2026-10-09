============================================================
 ĐỒ ÁN QUẢN LÝ CỬA HÀNG KÍNH TINH NHÃN
 Tình trạng hiện tại: 
============================================================

1. NHỮNG PHẦN ĐÃ LÀM
   Hiện tại nhóm mới làm phần khung dự án và kết nối cơ sở dữ liệu,
   chưa làm các chức năng chính.

   - Tạo thư mục riêng cho Nhân viên (employee/) và Quản lý (manager/).
   - Tạo cơ sở dữ liệu gồm 7 bảng và một số dữ liệu mẫu trong
     database/tinh_nhan.sql.
   - Tạo kết nối MySQL bằng PDO tại config/database.php.
   - Làm chức năng đăng nhập, đăng xuất và kiểm tra quyền theo vai trò:
     login.php, logout.php, includes/auth.php,
     includes/employee_guard.php, includes/manager_guard.php.
   - Tạo các phần giao diện dùng chung như header, thanh điều hướng và footer:
     includes/layout/header_employee.php, header_manager.php, footer.php.
   - Có một số hàm hỗ trợ sản phẩm trong includes/product_helper.php.
   - Tạo CSS dùng chung trong assets/css/style.css.
   - Tạo trước 13 trang khung (8 trang Nhân viên và 5 trang Quản lý).
     Các trang hiện kiểm tra quyền, kết nối cơ sở dữ liệu và hiển thị tên
     chức năng cùng mã UC. Hai trang Dashboard có lấy thử số liệu từ CSDL.

2. NHỮNG PHẦN CHƯA LÀM
   Các chức năng chính dự kiến làm:
   UC-02 Lập hóa đơn
   UC-04 Quản lý khách hàng
   UC-05 Xem sản phẩm
   UC-07 Nhập kho

3. CÁC TÀI KHOẢN CÓ THỂ ĐĂNG NHẬP
   Quản lý:  admin      / manager123
   Nhân viên: nhanvien1 / nhanvien123


4. CÁC BƯỚC ĐÃ KIỂM TRA
   - Đăng nhập admin và kiểm tra Dashboard Quản lý có hiện số liệu.
   - Đăng nhập nhanvien1 và kiểm tra Dashboard Nhân viên có hiện số liệu.
   - Bấm các mục trên thanh điều hướng để kiểm tra trang khung tương ứng.
   - Thử để Nhân viên mở /doan1/manager/index.php thì bị chặn (403).
   - Thử mở trang khi chưa đăng nhập thì được chuyển về trang đăng nhập.
   - Đăng xuất thì quay lại trang đăng nhập.
============================================================
