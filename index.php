<?php 
include 'includes/header.php'; 
include 'connect.php';

// Thực hiện truy vấn dữ liệu thực tế từ hệ thống
$total_products = $conn->query("SELECT COUNT(*) AS c FROM san_pham")->fetch_assoc()['c'] ?? 0;
$total_imports = $conn->query("SELECT COUNT(*) AS c FROM phieu_nhap")->fetch_assoc()['c'] ?? 0;
$sum_imports = $conn->query("SELECT SUM(tong_tien) AS sum FROM phieu_nhap")->fetch_assoc()['sum'] ?? 0;

// Lấy doanh thu thực 7 ngày gần nhất từ bảng hoa_don
$revenue_labels = [];
$revenue_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime("-$i days"));
    $revenue_labels[] = $label;

    $res_rev = $conn->query("
        SELECT COALESCE(SUM(tong_tien), 0) AS doanh_thu 
        FROM hoa_don 
        WHERE DATE(created_at) = '$date'
    ");
    $revenue_data[] = $res_rev ? (float)$res_rev->fetch_assoc()['doanh_thu'] : 0;
}
$revenue_labels_json = json_encode($revenue_labels);
$revenue_data_json   = json_encode($revenue_data);

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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Biểu đồ doanh thu (7 ngày gần nhất)</h5>
            </div>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="revenueChart"></canvas>
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById('revenueChart');
    if (ctx) {
        // Dữ liệu doanh thu thực từ DB (7 ngày gần nhất)
        var labels = <?= $revenue_labels_json ?>;
        var data_points = <?= $revenue_data_json ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: data_points,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(54, 162, 235, 1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('vi-VN') + ' ₫';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toLocaleString('vi-VN') + ' ₫';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
