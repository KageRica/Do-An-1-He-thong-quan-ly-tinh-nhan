<?php
// ============================================================
// includes/layout/footer.php
// Footer dùng chung cho MỌI trang (cả khu vực Nhân viên và Quản lý).
// Chỉ đóng các thẻ mà header_employee.php / header_manager.php đã mở
// (.main-content, body, html). Mỗi trang vẫn tự in khối <script>
// riêng của mình (nếu có) TRƯỚC khi require file này.
//
// Cách dùng (đặt ở cuối mỗi trang, thay cho đoạn
// "</div></body></html>" trước đây):
//
//   require __DIR__ . '/../includes/layout/footer.php';
// ============================================================
?>
    </div>

</body>
</html>
