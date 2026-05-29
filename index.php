<?php 
include 'includes/header.php'; 
include 'connect.php';

// Thực hiện truy vấn dữ liệu thực tế từ hệ thống
$total_products = $conn->query("SELECT COUNT(*) AS c FROM san_pham")->fetch_assoc()['c'] ?? 0;
$total_imports = $conn->query("SELECT COUNT(*) AS c FROM phieu_nhap")->fetch_assoc()['c'] ?? 0;
$sum_imports = $conn->query("SELECT SUM(tong_tien) AS sum FROM phieu_nhap")->fetch_assoc()['sum'] ?? 0;

// Lấy danh sách sản phẩm có nhiều hàng trong kho nhất
$top_stock = [];
$res_stock = $conn->query("
    SELECT sp.ten_san_pham, dm.ten_danh_muc, tk.so_luong 
    FROM ton_kho tk 
    JOIN san_pham sp ON tk.id_san_pham = sp.id 
    LEFT JOIN danh_muc dm ON sp.id_danh_muc = dm.id
    ORDER BY tk.so_luong DESC LIMIT 5
");
if ($res_stock) {
    while($r = $res_stock->fetch_assoc()){
        $top_stock[] = $r;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Thống kê hệ thống</h3>
        <p class="text-muted mb-0">Tổng quan về hàng hóa và dữ liệu kho</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0 border-start border-primary border-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-box-open"></i>
                </div>
            </div>
            <p class="text-muted mb-1">Tổng loại sản phẩm</p>
            <h2 class="fw-bold mb-0"><?= number_format($total_products) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0 border-start border-success border-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <p class="text-muted mb-1">Số lượng phiếu nhập</p>
            <h2 class="fw-bold mb-0"><?= number_format($total_imports) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0 border-start border-danger border-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <p class="text-muted mb-1">Tổng chi phí nhập hàng</p>
            <h2 class="fw-bold mb-0 text-danger"><?= number_format($sum_imports, 0, ',', '.') ?> <span class="text-decoration-underline">đ</span></h2>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card p-4 h-100 shadow-sm border-0">
            <h5 class="fw-bold mb-4">Hoạt động thời gian thực</h5>
            <div class="text-center text-muted py-5">
                <i class="fas fa-chart-area fa-4x mb-3 opacity-25"></i>
                <p>Biểu đồ doanh thu đang ẩn<br> <small>(Cần tạo bảng <code>hoa_don</code> để thống kê doanh thu bán từ POS)</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card p-4 h-100 shadow-sm border-0">
            <h5 class="fw-bold mb-4">Tồn kho nhiều nhất</h5>
            
            <?php foreach($top_stock as $index => $item): 
                $colors = ['warning', 'secondary', 'danger', 'info', 'primary'];
                $bg_color = $colors[$index % count($colors)];
            ?>
            <div class="d-flex align-items-center mb-4">
                <div class="bg-<?= $bg_color ?> bg-opacity-25 text-<?= $bg_color ?> rounded p-2 me-3 fw-bold" style="width: 35px; text-align: center;">
                    #<?= $index + 1 ?>
                </div>
                <div class="flex-grow-1" style="overflow: hidden;">
                    <h6 class="mb-0 fw-bold text-truncate"><?= htmlspecialchars($item['ten_san_pham']) ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($item['ten_danh_muc'] ?? 'Không rõ') ?></small>
                </div>
                <div class="text-end ms-2">
                    <div class="fw-bold"><?= $item['so_luong'] ?></div>
                    <small class="text-success">có sẵn</small>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($top_stock)): ?>
            <div class="text-center text-muted mt-4">
                Chưa có dữ liệu tồn kho.
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>