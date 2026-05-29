<?php
session_start();

// Nếu đã đăng nhập thì tự động chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'connect.php';

// Tự động tạo tài khoản admin mặc định với mật khẩu đã băm (nếu DB chưa có tài khoản nào)
$check_empty = $conn->query("SELECT COUNT(*) as c FROM tai_khoan")->fetch_assoc()['c'];
if ($check_empty == 0) {
    $hashed_pass = password_hash('123456', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO tai_khoan (ten_dang_nhap, mat_khau, ho_ten, vai_tro, trang_thai) VALUES ('admin', '$hashed_pass', 'Quản trị viên', 'admin', 1)");
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Tìm tài khoản (chỉ những tài khoản đang hoạt động trang_thai = 1)
    $stmt = $conn->prepare("SELECT * FROM tai_khoan WHERE ten_dang_nhap = ? AND trang_thai = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Kiểm tra mật khẩu (đã băm)
        if (password_verify($password, $user['mat_khau'])) {
            // Đăng nhập thành công, khởi tạo Sessions
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['ten_dang_nhap'] = $user['ten_dang_nhap'];
            $_SESSION['ho_ten'] = $user['ho_ten'];
            $_SESSION['vai_tro'] = $user['vai_tro'];

            header("Location: index.php");
            exit();
        } else {
            $error = "Tài khoản hoặc mật khẩu không chính xác!";
        }
    } else {
        $error = "Tài khoản hoặc mật khẩu không chính xác, hoặc tài khoản đã bị khóa!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Thanh Hậu POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Segoe UI', sans-serif;}
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .brand { font-size: 1.5rem; font-weight: bold; text-align: center; margin-bottom: 30px; color: #111827; }
        .btn-custom { background-color: #10b981; color: white; border: none; border-radius: 8px; padding: 10px 16px; font-weight: 500; }
        .btn-custom:hover { background-color: #059669; color: white; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand">
        <i class="fas fa-store text-success me-2"></i> Thanh Hậu POS
    </div>
    <h5 class="text-center mb-4 text-muted">Đăng nhập hệ thống</h5>

    <?php if ($error): ?>
        <div class="alert alert-danger p-2 text-center" role="alert">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="login" value="1">
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold">TÊN ĐĂNG NHẬP</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                <input type="text" name="username" class="form-control" required placeholder="Nhập tên đăng nhập...">
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label text-muted small fw-bold">MẬT KHẨU</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                <input type="password" name="password" class="form-control" required placeholder="Nhập mật khẩu...">
            </div>
        </div>
        <button type="submit" class="btn btn-custom w-100">Đăng nhập</button>
    </form>

    <div class="mt-4 text-center">
        <small class="text-muted">TK mặc định: <b>admin</b> - MK: <b>123456</b></small>
    </div>
</div>

</body>
</html>