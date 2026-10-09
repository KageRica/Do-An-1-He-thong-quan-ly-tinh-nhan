<?php
// ============================================================
// login.php
// Trang đăng nhập duy nhất cho cả 2 role (employee / manager).
// Không có chức năng đăng ký - tài khoản do Quản lý tạo sẵn.
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Nếu người dùng đã đăng nhập rồi thì không cần vào lại trang login,
// điều hướng thẳng đến đúng khu vực của họ.
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'employee') {
        header("Location: /doan1/employee/index.php");
        exit();
    } elseif ($_SESSION['role'] === 'manager') {
        header("Location: /doan1/manager/index.php");
        exit();
    }
}

$error_message = "";

// Chỉ xử lý khi người dùng bấm nút đăng nhập (phương thức POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lấy dữ liệu người dùng nhập, loại bỏ khoảng trắng thừa
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error_message = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
    } else {
        // Tìm tài khoản theo username bằng prepared statement
        $stmt = $pdo->prepare("SELECT user_id, username, password, full_name, role, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            // Không tìm thấy username
            $error_message = "Tên đăng nhập hoặc mật khẩu không đúng.";
        } elseif ($user['status'] !== 'active') {
            // Tài khoản tồn tại nhưng đã bị khóa/ngừng hoạt động
            $error_message = "Tài khoản của bạn hiện không hoạt động. Vui lòng liên hệ Quản lý.";
        } elseif (!password_verify($password, $user['password'])) {
            // So sánh mật khẩu người dùng nhập với hash trong database
            $error_message = "Tên đăng nhập hoặc mật khẩu không đúng.";
        } else {
            // Đăng nhập thành công -> tạo session mới để chống session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            // Điều hướng theo đúng role
            if ($user['role'] === 'employee') {
                header("Location: /doan1/employee/index.php");
                exit();
            } else {
                header("Location: /doan1/manager/index.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Tinh Nhãn</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background-color: #f2f5f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #10181f;
        }
        .login-box {
            background-color: #fff;
            padding: 36px 40px;
            border-radius: 16px;
            border: 1px solid #e9edef;
            box-shadow: 0 4px 16px rgba(16, 24, 31, 0.08);
            width: 340px;
        }
        .login-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .login-brand::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #0d6b62;
            box-shadow: 0 0 0 3px #e3f2ef;
        }
        .login-brand span {
            font-family: "Space Grotesk", sans-serif;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: -0.01em;
        }
        .login-box h2 {
            font-family: "Space Grotesk", sans-serif;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 22px 0;
            color: #10181f;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #455e6e;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            box-sizing: border-box;
            border: 1px solid #4c5f68;
            border-radius: 6px;
            font-size: 14.5px;
            font-family: inherit;
            min-height: 42px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0d6b62;
            box-shadow: 0 0 0 3px #e3f2ef;
        }
        .btn-login {
            width: 100%;
            padding: 11px;
            background-color: #0d6b62;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            min-height: 44px;
        }
        .btn-login:hover {
            background-color: #0a544d;
        }
        .error-message {
            background-color: #fbe7e5;
            color: #b3261e;
            border: 1px solid #f2c3bf;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-brand"><span>Tinh Nhãn</span></div>
        <h2>Đăng nhập hệ thống</h2>

        <?php if ($error_message !== ''): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Tên đăng nhập</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
