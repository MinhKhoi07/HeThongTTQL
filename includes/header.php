<?php
session_start();

// Nếu chưa đăng nhập thì chuyển hướng ra trang login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Hậu - Quản lý cửa hàng tiện lợi</title>
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
        <a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Thống kê
        </a>
        <a href="sales.php" class="<?= $currentPage == 'sales.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> Bán hàng
        </a>
        <a href="products.php" class="<?= $currentPage == 'products.php' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Sản phẩm
        </a>
        <a href="categories.php" class="<?= $currentPage == 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Danh mục
        </a>
        <a href="inventory.php" class="<?= $currentPage == 'inventory.php' ? 'active' : '' ?>">
            <i class="fas fa-warehouse"></i> Tồn kho
        </a>
        <a href="imports.php" class="<?= $currentPage == 'imports.php' ? 'active' : '' ?>">
            <i class="fas fa-download"></i> Nhập hàng
        </a>
        <a href="accounts.php" class="<?= $currentPage == 'accounts.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Tài khoản
        </a>
        <div style="position: absolute; bottom: 20px; width: 100%;">
            <a href="logout.php" style="color: #9ca3af;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <span class="text-muted">Thứ Năm, 28 tháng 5, 2026</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-bell text-muted fs-5 position-relative">
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
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