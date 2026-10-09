<?php
// ============================================================
// logout.php
// Đăng xuất người dùng: xóa toàn bộ session và chuyển về login.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';

doLogout();

header("Location: /doan1/login.php");
exit();
