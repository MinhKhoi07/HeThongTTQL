<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu chưa đăng nhập thì chuyển hướng ra trang login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Kiểm tra quyền (Chỉ Quản trị viên mới thấy tất cả, nhân viên bị giấu bớt menu)
$role = $_SESSION['vai_tro'] ?? 'nhanvien';
$isAdmin = ($role === 'Quản trị viên' || $role === 'admin');

// Đếm thông báo (Cảnh báo tồn kho & hết hạn)
include_once __DIR__ . '/../connect.php';
$alert_count = 0;
// 1. SP dưới mức tồn tối thiểu
$sql_low_stock = "SELECT COUNT(*) as total FROM (SELECT sp.id FROM san_pham sp LEFT JOIN ton_kho tk ON sp.id = tk.id_san_pham GROUP BY sp.id HAVING IFNULL(SUM(tk.so_luong), 0) <= MAX(sp.ton_toi_thieu)) as temp";
$result_low = $conn->query($sql_low_stock);
if ($result_low) $alert_count += $result_low->fetch_assoc()['total'];

// 2. SP sắp hết hạn hoặc đã hết hạn (trong vòng 30 ngày)
$sql_exp = "SELECT COUNT(*) as total FROM san_pham WHERE han_su_dung IS NOT NULL AND han_su_dung <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$result_exp = $conn->query($sql_exp);
if ($result_exp) $alert_count += $result_exp->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Hậu - Quản lý cửa hàng tiện lợi</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fas fa-store text-success"></i> Thanh Hậu
        </div>
        
        <?php if ($isAdmin): ?>
        <a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Thống kê
        </a>
        <?php endif; ?>
        
        <a href="sales.php" class="<?= $currentPage == 'sales.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> Bán hàng
        </a>
        <a href="products.php" class="<?= $currentPage == 'products.php' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Sản phẩm
        </a>
        
        <?php if ($isAdmin): ?>
        <a href="categories.php" class="<?= $currentPage == 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Danh mục
        </a>
        <a href="promotions.php" class="<?= $currentPage == 'promotions.php' ? 'active' : '' ?>">
            <i class="fas fa-percentage"></i> Khuyến mãi
        </a>
        <?php endif; ?>
        
        <a href="inventory.php" class="<?= $currentPage == 'inventory.php' ? 'active' : '' ?>">
            <i class="fas fa-warehouse"></i> Tồn kho
        </a>
        
        <?php if ($isAdmin): ?>
        <a href="imports.php" class="<?= $currentPage == 'imports.php' ? 'active' : '' ?>">
            <i class="fas fa-download"></i> Nhập hàng
        </a>
        <a href="accounts.php" class="<?= $currentPage == 'accounts.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Tài khoản
        </a>
        <?php endif; ?>
        
        <div style="position: absolute; bottom: 20px; width: 100%;">
            <a href="logout.php" style="color: #9ca3af;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <span id="realtime-clock" class="text-muted"></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-bell text-muted fs-5 position-relative" title="Cảnh báo hàng hóa" style="cursor: pointer;">
                    <?php if ($alert_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        <?= $alert_count ?>
                    </span>
                    <?php endif; ?>
                </i>
                <div class="d-flex align-items-center gap-2 border rounded-pill px-2 py-1 bg-light cursor-pointer">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="fw-medium me-2"><?= htmlspecialchars($_SESSION['ho_ten'] ?? 'Admin') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">