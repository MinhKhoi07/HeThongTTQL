<?php 
include 'includes/header.php'; 
include 'connect.php';

// -------------------------------------------------------
// Bộ lọc tháng / năm
// -------------------------------------------------------
$sel_month = (int)($_GET['month'] ?? date('m'));
$sel_year  = (int)($_GET['year']  ?? date('Y'));

// Giới hạn hợp lệ
if ($sel_month < 1 || $sel_month > 12) $sel_month = (int)date('m');
if ($sel_year  < 2000 || $sel_year > (int)date('Y') + 1) $sel_year = (int)date('Y');

$month_start = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$month_end   = date('Y-m-t', strtotime($month_start));   // ngày cuối tháng
$today       = date('Y-m-d');

// Nhãn tháng đang xem
$month_label = date('m/Y', strtotime($month_start));
$is_current  = ($sel_month == (int)date('m') && $sel_year == (int)date('Y'));

// -------------------------------------------------------
// Tổng quan
// -------------------------------------------------------
$total_products = $conn->query("SELECT COUNT(*) AS c FROM san_pham")->fetch_assoc()['c'] ?? 0;
$total_imports  = $conn->query("SELECT COUNT(*) AS c FROM phieu_nhap")->fetch_assoc()['c'] ?? 0;
$sum_imports    = (float)($conn->query("SELECT SUM(tong_tien) AS s FROM phieu_nhap")->fetch_assoc()['s'] ?? 0);

// Doanh thu tháng được chọn (từ hóa đơn)
$doanh_thu_thang = (float)$conn->query("
    SELECT COALESCE(SUM(tong_tien),0) AS s 
    FROM hoa_don 
    WHERE DATE(created_at) >= '$month_start' AND DATE(created_at) <= '$month_end'
")->fetch_assoc()['s'];

// Giá vốn hàng đã bán tháng được chọn
// = SUM(số lượng bán × giá_nhap của sản phẩm đó)
$gia_von_thang = (float)$conn->query("
    SELECT COALESCE(SUM(ct.so_luong * sp.gia_nhap), 0) AS s
    FROM chi_tiet_hoa_don ct
    JOIN hoa_don hd ON ct.id_hoa_don = hd.id
    JOIN san_pham sp ON ct.id_san_pham = sp.id
    WHERE DATE(hd.created_at) >= '$month_start' AND DATE(hd.created_at) <= '$month_end'
")->fetch_assoc()['s'];

$loi_nhuan_thang = $doanh_thu_thang - $gia_von_thang;
$ty_le_ln = $doanh_thu_thang > 0
    ? round($loi_nhuan_thang / $doanh_thu_thang * 100, 1)
    : 0;

// Tháng trước (so sánh) — tính từ tháng được chọn
$prev_ts    = mktime(0, 0, 0, $sel_month - 1, 1, $sel_year);
$prev_start = date('Y-m-01', $prev_ts);
$prev_end   = date('Y-m-t',  $prev_ts);
$doanh_thu_prev = (float)$conn->query("
    SELECT COALESCE(SUM(tong_tien),0) AS s 
    FROM hoa_don 
    WHERE DATE(created_at) >= '$prev_start' AND DATE(created_at) <= '$prev_end'
")->fetch_assoc()['s'];
$gia_von_prev = (float)$conn->query("
    SELECT COALESCE(SUM(ct.so_luong * sp.gia_nhap), 0) AS s
    FROM chi_tiet_hoa_don ct
    JOIN hoa_don hd ON ct.id_hoa_don = hd.id
    JOIN san_pham sp ON ct.id_san_pham = sp.id
    WHERE DATE(hd.created_at) >= '$prev_start' AND DATE(hd.created_at) <= '$prev_end'
")->fetch_assoc()['s'];
$loi_nhuan_prev = $doanh_thu_prev - $gia_von_prev;

// % thay đổi so với tháng trước
function pct_change(float $new, float $old): string {
    if ($old == 0) return $new > 0 ? '+∞%' : '—';
    $p = round(($new - $old) / abs($old) * 100, 1);
    return ($p >= 0 ? '+' : '') . $p . '%';
}
$dt_change = pct_change($doanh_thu_thang, $doanh_thu_prev);
$ln_change = pct_change($loi_nhuan_thang, $loi_nhuan_prev);

// -------------------------------------------------------
// Dữ liệu biểu đồ: từng ngày trong tháng được chọn
// -------------------------------------------------------
$chart_labels   = [];
$chart_revenue  = [];
$chart_cost     = [];
$chart_profit   = [];

$days_in_month = (int)date('t', strtotime($month_start));
// Nếu là tháng hiện tại, chỉ hiện đến hôm nay
$last_day = ($is_current) ? (int)date('d') : $days_in_month;

for ($d = 1; $d <= $last_day; $d++) {
    $date  = sprintf('%04d-%02d-%02d', $sel_year, $sel_month, $d);
    $chart_labels[] = $d . '/' . $sel_month;

    $rev = (float)$conn->query("
        SELECT COALESCE(SUM(tong_tien),0) AS s FROM hoa_don WHERE DATE(created_at)='$date'
    ")->fetch_assoc()['s'];

    $cost = (float)$conn->query("
        SELECT COALESCE(SUM(ct.so_luong * sp.gia_nhap),0) AS s
        FROM chi_tiet_hoa_don ct
        JOIN hoa_don hd ON ct.id_hoa_don = hd.id
        JOIN san_pham sp ON ct.id_san_pham = sp.id
        WHERE DATE(hd.created_at) = '$date'
    ")->fetch_assoc()['s'];

    $chart_revenue[] = $rev;
    $chart_cost[]    = $cost;
    $chart_profit[]  = $rev - $cost;
}

// -------------------------------------------------------
// Tồn kho nhiều nhất
// -------------------------------------------------------
$top_stock = [];
$res_stock = $conn->query("
    SELECT sp.ten_san_pham, dm.ten_danh_muc, tk.so_luong 
    FROM ton_kho tk 
    JOIN san_pham sp ON tk.id_san_pham = sp.id 
    LEFT JOIN danh_muc dm ON sp.id_danh_muc = dm.id
    ORDER BY tk.so_luong DESC LIMIT 5
");
if ($res_stock) while ($r = $res_stock->fetch_assoc()) $top_stock[] = $r;

// -------------------------------------------------------
// Lợi nhuận theo tháng (12 tháng gần nhất) — cho biểu đồ dưới
// -------------------------------------------------------
$monthly_labels  = [];
$monthly_revenue = [];
$monthly_profit  = [];
for ($m = 11; $m >= 0; $m--) {
    $ms = date('Y-m-01', strtotime("-$m months"));
    $me = date('Y-m-t',  strtotime("-$m months"));
    $monthly_labels[] = date('m/Y', strtotime($ms));

    $r = (float)$conn->query("
        SELECT COALESCE(SUM(tong_tien),0) AS s FROM hoa_don 
        WHERE DATE(created_at) BETWEEN '$ms' AND '$me'
    ")->fetch_assoc()['s'];

    $c = (float)$conn->query("
        SELECT COALESCE(SUM(ct.so_luong * sp.gia_nhap),0) AS s
        FROM chi_tiet_hoa_don ct
        JOIN hoa_don hd ON ct.id_hoa_don = hd.id
        JOIN san_pham sp ON ct.id_san_pham = sp.id
        WHERE DATE(hd.created_at) BETWEEN '$ms' AND '$me'
    ")->fetch_assoc()['s'];

    $monthly_revenue[] = $r;
    $monthly_profit[]  = $r - $c;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Thống kê hệ thống</h3>
        <p class="text-muted mb-0">Tổng quan doanh thu, lợi nhuận và hàng hóa</p>
    </div>
    <!-- Bộ chọn tháng / năm -->
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="text-muted small mb-0">Tháng:</label>
        <select name="month" class="form-select form-select-sm" style="width:90px" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m == $sel_month ? 'selected' : '' ?>>
                Tháng <?= sprintf('%02d', $m) ?>
            </option>
            <?php endfor; ?>
        </select>
        <select name="year" class="form-select form-select-sm" style="width:90px" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= 2023; $y--): ?>
            <option value="<?= $y ?>" <?= $y == $sel_year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <span class="badge bg-<?= $is_current ? 'success' : 'secondary' ?> px-3 py-2">
            <?= $is_current ? 'Tháng này' : $month_label ?>
        </span>
    </form>
</div>

<!-- ===================== WIDGET HÀNG 1: TỔNG QUAN ===================== -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-4 shadow-sm border-0 border-start border-primary border-4">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-2">
                <i class="fas fa-box-open"></i>
            </div>
            <p class="text-muted mb-1 small">Tổng loại sản phẩm</p>
            <h2 class="fw-bold mb-0"><?= number_format($total_products) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-4 shadow-sm border-0 border-start border-info border-4">
            <div class="stat-icon bg-info bg-opacity-10 text-info mb-2">
                <i class="fas fa-file-invoice"></i>
            </div>
            <p class="text-muted mb-1 small">Phiếu nhập hàng</p>
            <h2 class="fw-bold mb-0"><?= number_format($total_imports) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-4 shadow-sm border-0 border-start border-success border-4">
            <div class="stat-icon bg-success bg-opacity-10 text-success mb-2">
                <i class="fas fa-chart-line"></i>
            </div>
            <p class="text-muted mb-1 small">Doanh thu tháng này</p>
            <h2 class="fw-bold mb-0 text-success"><?= number_format($doanh_thu_thang, 0, ',', '.') ?> đ</h2>
            <small class="<?= str_contains($dt_change, '+') ? 'text-success' : 'text-danger' ?>">
                <?= $dt_change ?> so tháng trước
            </small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-4 shadow-sm border-0 border-start border-warning border-4">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning mb-2">
                <i class="fas fa-coins"></i>
            </div>
            <p class="text-muted mb-1 small">Lợi nhuận tháng này</p>
            <h2 class="fw-bold mb-0 <?= $loi_nhuan_thang >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= number_format($loi_nhuan_thang, 0, ',', '.') ?> đ
            </h2>
            <small class="<?= str_contains($ln_change, '+') ? 'text-success' : 'text-danger' ?>">
                <?= $ln_change ?> so tháng trước
                <?php if ($ty_le_ln > 0): ?>
                    · <span class="text-muted"><?= $ty_le_ln ?>% biên LN</span>
                <?php endif; ?>
            </small>
        </div>
    </div>
</div>

<!-- ===================== WIDGET HÀNG 2: PHÂN TÍCH LỢI NHUẬN ===================== -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0">
            <h6 class="fw-bold text-muted mb-3">Phân tích tháng <?= $month_label ?></h6>
            <?php
            $items_profit = [
                ['label' => 'Doanh thu',  'val' => $doanh_thu_thang, 'color' => 'primary',  'icon' => 'fa-arrow-up'],
                ['label' => 'Giá vốn',    'val' => $gia_von_thang,   'color' => 'danger',   'icon' => 'fa-arrow-down'],
                ['label' => 'Lợi nhuận', 'val' => $loi_nhuan_thang, 'color' => $loi_nhuan_thang >= 0 ? 'success' : 'danger', 'icon' => 'fa-coins'],
            ];
            foreach ($items_profit as $it):
            ?>
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <span class="text-muted small">
                    <i class="fas <?= $it['icon'] ?> text-<?= $it['color'] ?> me-2"></i><?= $it['label'] ?>
                </span>
                <span class="fw-bold text-<?= $it['color'] ?>">
                    <?= number_format($it['val'], 0, ',', '.') ?> đ
                </span>
            </div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-between align-items-center pt-3">
                <span class="text-muted small"><i class="fas fa-percent me-2 text-info"></i>Biên lợi nhuận</span>
                <span class="fw-bold text-info"><?= $ty_le_ln ?>%</span>
            </div>
            <!-- Mini progress bar biên LN -->
            <div class="progress mt-2" style="height:6px">
                <div class="progress-bar <?= $ty_le_ln >= 20 ? 'bg-success' : ($ty_le_ln >= 10 ? 'bg-warning' : 'bg-danger') ?>"
                     style="width:<?= min(100, max(0, $ty_le_ln)) ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card p-4 shadow-sm border-0">
            <h6 class="fw-bold text-muted mb-3">Doanh thu & Lợi nhuận theo ngày — Tháng <?= $month_label ?></h6>
            <div style="position:relative;height:180px">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ===================== BIỂU ĐỒ 12 THÁNG + TỒN KHO ===================== -->
<div class="row g-3">
    <div class="col-md-8 mb-4">
        <div class="card p-4 h-100 shadow-sm border-0">
            <h5 class="fw-bold mb-4">Doanh thu & Lợi nhuận 12 tháng gần nhất</h5>
            <div style="position: relative; height: 280px; width: 100%;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card p-4 h-100 shadow-sm border-0">
            <h5 class="fw-bold mb-4">Tồn kho nhiều nhất</h5>
            <?php foreach ($top_stock as $index => $item):
                $colors = ['warning', 'secondary', 'danger', 'info', 'primary'];
                $bg_color = $colors[$index % count($colors)];
            ?>
            <div class="d-flex align-items-center mb-4">
                <div class="bg-<?= $bg_color ?> bg-opacity-25 text-<?= $bg_color ?> rounded p-2 me-3 fw-bold"
                     style="width:35px;text-align:center">#<?= $index + 1 ?></div>
                <div class="flex-grow-1" style="overflow:hidden">
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
            <div class="text-center text-muted mt-4">Chưa có dữ liệu tồn kho.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    // ── Biểu đồ 7 ngày: Doanh thu / Giá vốn / Lợi nhuận ──────────────
    const wCtx = document.getElementById('weeklyChart');
    if (wCtx) {
        new Chart(wCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Doanh thu',
                        data:  <?= json_encode($chart_revenue) ?>,
                        backgroundColor: 'rgba(59,130,246,0.6)',
                        borderRadius: 4,
                        order: 2,
                    },
                    {
                        label: 'Giá vốn',
                        data:  <?= json_encode($chart_cost) ?>,
                        backgroundColor: 'rgba(239,68,68,0.45)',
                        borderRadius: 4,
                        order: 2,
                    },
                    {
                        label: 'Lợi nhuận',
                        data:  <?= json_encode($chart_profit) ?>,
                        type:  'line',
                        borderColor:  'rgba(16,185,129,1)',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(16,185,129,1)',
                        fill: true,
                        tension: 0.4,
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('vi-VN') + ' đ'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'tr' : v.toLocaleString('vi-VN') }
                    }
                }
            }
        });
    }

    // ── Biểu đồ 12 tháng: Doanh thu + Lợi nhuận ─────────────────────
    const mCtx = document.getElementById('monthlyChart');
    if (mCtx) {
        new Chart(mCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthly_labels) ?>,
                datasets: [
                    {
                        label: 'Doanh thu',
                        data:  <?= json_encode($monthly_revenue) ?>,
                        backgroundColor: 'rgba(59,130,246,0.5)',
                        borderRadius: 4,
                        order: 2,
                    },
                    {
                        label: 'Lợi nhuận',
                        data:  <?= json_encode($monthly_profit) ?>,
                        type: 'line',
                        borderColor: 'rgba(16,185,129,1)',
                        backgroundColor: 'rgba(16,185,129,0.12)',
                        borderWidth: 2.5,
                        pointBackgroundColor: 'rgba(16,185,129,1)',
                        fill: true,
                        tension: 0.4,
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('vi-VN') + ' đ'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'tr' : v.toLocaleString('vi-VN') }
                    }
                }
            }
        });
    }
});
</script>
