<?php
include 'connect.php';

// -------------------------------------------------------
// Bộ lọc thời gian
// -------------------------------------------------------
$filter   = $_GET['filter']   ?? 'today';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';

$today      = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');
$year_start  = date('Y-01-01');

switch ($filter) {
    case 'today':
        $sql_where = "DATE(hd.created_at) = '$today'";
        $label     = 'Hôm nay (' . date('d/m/Y') . ')';
        break;
    case 'yesterday':
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $sql_where = "DATE(hd.created_at) = '$yesterday'";
        $label     = 'Hôm qua (' . date('d/m/Y', strtotime('-1 day')) . ')';
        break;
    case 'week':
        $sql_where = "DATE(hd.created_at) >= '$week_start' AND DATE(hd.created_at) <= '$today'";
        $label     = 'Tuần này (từ ' . date('d/m', strtotime($week_start)) . ')';
        break;
    case 'month':
        $sql_where = "DATE(hd.created_at) >= '$month_start' AND DATE(hd.created_at) <= '$today'";
        $label     = 'Tháng này (' . date('m/Y') . ')';
        break;
    case 'year':
        $sql_where = "DATE(hd.created_at) >= '$year_start' AND DATE(hd.created_at) <= '$today'";
        $label     = 'Năm nay (' . date('Y') . ')';
        break;
    case 'custom':
        $df = $date_from ?: $today;
        $dt = $date_to   ?: $today;
        $sql_where = "DATE(hd.created_at) >= '$df' AND DATE(hd.created_at) <= '$dt'";
        $label     = 'Từ ' . date('d/m/Y', strtotime($df)) . ' → ' . date('d/m/Y', strtotime($dt));
        break;
    default:
        $sql_where = "DATE(hd.created_at) = '$today'";
        $label     = 'Hôm nay';
}

// -------------------------------------------------------
// Tổng quan trong kỳ
// -------------------------------------------------------
$stat = $conn->query("
    SELECT
        COUNT(*)          AS so_don,
        COALESCE(SUM(hd.tong_tien), 0) AS doanh_thu,
        COALESCE(SUM(ct.tong_sl), 0)   AS tong_sp
    FROM hoa_don hd
    LEFT JOIN (
        SELECT id_hoa_don, SUM(so_luong) AS tong_sl
        FROM chi_tiet_hoa_don
        GROUP BY id_hoa_don
    ) ct ON ct.id_hoa_don = hd.id
    WHERE $sql_where
")->fetch_assoc();

$so_don    = (int)$stat['so_don'];
$doanh_thu = (float)$stat['doanh_thu'];
$tong_sp   = (int)$stat['tong_sp'];
$avg_don   = $so_don > 0 ? $doanh_thu / $so_don : 0;

// -------------------------------------------------------
// Biểu đồ doanh thu theo ngày (trong kỳ hiện tại, tối đa 30 ngày)
// -------------------------------------------------------
$chart_data = [];
if ($filter === 'today' || $filter === 'yesterday') {
    // Theo giờ trong ngày
    $day = ($filter === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : $today;
    for ($h = 0; $h <= 23; $h++) {
        $hh = sprintf('%02d', $h);
        $res = $conn->query("
            SELECT COALESCE(SUM(tong_tien),0) AS rev
            FROM hoa_don
            WHERE DATE(created_at) = '$day' AND HOUR(created_at) = $h
        ");
        $chart_data[$hh . ':00'] = (float)$res->fetch_assoc()['rev'];
    }
} else {
    // Theo ngày
    switch ($filter) {
        case 'week':  $start = $week_start;  $end = $today; break;
        case 'month': $start = $month_start; $end = $today; break;
        case 'year':  $start = $year_start;  $end = $today; break;
        case 'custom':
            $start = $date_from ?: $today;
            $end   = $date_to   ?: $today;
            break;
        default: $start = $today; $end = $today;
    }
    $cur = strtotime($start);
    $fin = strtotime($end);
    while ($cur <= $fin) {
        $d   = date('Y-m-d', $cur);
        $lbl = date('d/m', $cur);
        $res = $conn->query("SELECT COALESCE(SUM(tong_tien),0) AS rev FROM hoa_don WHERE DATE(created_at)='$d'");
        $chart_data[$lbl] = (float)$res->fetch_assoc()['rev'];
        $cur = strtotime('+1 day', $cur);
    }
}

// -------------------------------------------------------
// Top sản phẩm bán chạy trong kỳ
// -------------------------------------------------------
$top_products = $conn->query("
    SELECT sp.ten_san_pham, sp.ma_sku,
           SUM(ct.so_luong) AS tong_sl,
           SUM(ct.thanh_tien) AS tong_tt
    FROM chi_tiet_hoa_don ct
    JOIN hoa_don hd ON ct.id_hoa_don = hd.id
    JOIN san_pham sp ON ct.id_san_pham = sp.id
    WHERE $sql_where
    GROUP BY ct.id_san_pham
    ORDER BY tong_sl DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// -------------------------------------------------------
// Danh sách đơn hàng (phân trang đơn giản)
// -------------------------------------------------------
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$total_rows = (int)$conn->query("SELECT COUNT(*) as c FROM hoa_don hd WHERE $sql_where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));

$orders = $conn->query("
    SELECT hd.id, hd.ma_hoa_don, hd.tong_tien, hd.created_at,
           COUNT(ct.id)       AS so_loai_sp,
           SUM(ct.so_luong)   AS tong_sl
    FROM hoa_don hd
    LEFT JOIN chi_tiet_hoa_don ct ON ct.id_hoa_don = hd.id
    WHERE $sql_where
    GROUP BY hd.id
    ORDER BY hd.created_at DESC
    LIMIT $per_page OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Lịch sử đơn hàng</h3>
        <p class="text-muted mb-0"><?= $label ?></p>
    </div>
</div>

<!-- ===================== BỘ LỌC ===================== -->
<div class="card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end" id="filterForm">
        <!-- Nút lọc nhanh -->
        <div class="col-auto">
            <?php
            $btns = [
                'today'     => 'Hôm nay',
                'yesterday' => 'Hôm qua',
                'week'      => 'Tuần này',
                'month'     => 'Tháng này',
                'year'      => 'Năm nay',
                'custom'    => 'Tùy chọn',
            ];
            foreach ($btns as $val => $lbl):
                $active = $filter === $val ? 'btn-primary' : 'btn-outline-secondary';
            ?>
            <button type="submit" name="filter" value="<?= $val ?>"
                    class="btn btn-sm <?= $active ?> me-1">
                <?= $lbl ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Khoảng tùy chọn -->
        <div class="col-auto d-flex align-items-center gap-2" id="custom_range"
             style="<?= $filter !== 'custom' ? 'display:none!important' : '' ?>">
            <input type="hidden" name="filter" value="custom">
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($date_from ?: $today) ?>">
            <span class="text-muted">→</span>
            <input type="date" name="date_to"   class="form-control form-control-sm"
                   value="<?= htmlspecialchars($date_to ?: $today) ?>">
            <button type="submit" class="btn btn-sm btn-primary">Xem</button>
        </div>
    </form>
</div>

<!-- ===================== WIDGET THỐNG KÊ ===================== -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card p-4 border-0 shadow-sm border-start border-primary border-4">
            <p class="text-muted mb-1 small">Số đơn hàng</p>
            <h2 class="fw-bold mb-0 text-primary"><?= number_format($so_don) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-4 border-0 shadow-sm border-start border-success border-4">
            <p class="text-muted mb-1 small">Doanh thu</p>
            <h2 class="fw-bold mb-0 text-success"><?= number_format($doanh_thu, 0, ',', '.') ?> đ</h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-4 border-0 shadow-sm border-start border-warning border-4">
            <p class="text-muted mb-1 small">Sản phẩm đã bán</p>
            <h2 class="fw-bold mb-0 text-warning"><?= number_format($tong_sp) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-4 border-0 shadow-sm border-start border-info border-4">
            <p class="text-muted mb-1 small">Trung bình / đơn</p>
            <h2 class="fw-bold mb-0 text-info"><?= number_format($avg_don, 0, ',', '.') ?> đ</h2>
        </div>
    </div>
</div>

<!-- ===================== BIỂU ĐỒ + TOP SP ===================== -->
<div class="row mb-4 g-3">
    <!-- Biểu đồ doanh thu -->
    <div class="col-md-8">
        <div class="card p-4 shadow-sm border-0 h-100">
            <h6 class="fw-bold mb-3">Doanh thu theo <?= ($filter === 'today' || $filter === 'yesterday') ? 'giờ' : 'ngày' ?></h6>
            <div style="position:relative;height:260px">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top sản phẩm bán chạy -->
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0 h-100">
            <h6 class="fw-bold mb-3">Top sản phẩm bán chạy</h6>
            <?php if (empty($top_products)): ?>
                <div class="text-center text-muted mt-4">
                    <i class="fas fa-box-open fa-2x mb-2 opacity-25 d-block"></i>
                    Chưa có dữ liệu
                </div>
            <?php else: ?>
                <?php
                $max_sl = max(array_column($top_products, 'tong_sl'));
                $colors = ['primary','success','warning','danger','info'];
                foreach ($top_products as $i => $tp):
                    $pct = $max_sl > 0 ? round($tp['tong_sl'] / $max_sl * 100) : 0;
                    $c   = $colors[$i % count($colors)];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold text-truncate" style="max-width:60%"
                              title="<?= htmlspecialchars($tp['ten_san_pham']) ?>">
                            <?= htmlspecialchars($tp['ten_san_pham']) ?>
                        </span>
                        <span class="small text-muted"><?= number_format($tp['tong_sl']) ?> cái</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-<?= $c ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================== DANH SÁCH ĐƠN HÀNG ===================== -->
<div class="card p-4 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Danh sách đơn hàng <span class="badge bg-secondary"><?= $total_rows ?></span></h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Mã hóa đơn</th>
                    <th>Thời gian</th>
                    <th>Số loại SP</th>
                    <th>Tổng SL</th>
                    <th>Thành tiền</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="fas fa-receipt fa-3x mb-3 opacity-25 d-block"></i>
                        Không có đơn hàng nào trong kỳ này.
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($orders as $idx => $od): ?>
                <tr>
                    <td class="text-muted small"><?= $offset + $idx + 1 ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($od['ma_hoa_don']) ?></td>
                    <td>
                        <div><?= date('H:i:s', strtotime($od['created_at'])) ?></div>
                        <small class="text-muted"><?= date('d/m/Y', strtotime($od['created_at'])) ?></small>
                    </td>
                    <td><?= $od['so_loai_sp'] ?> loại</td>
                    <td><?= number_format($od['tong_sl']) ?></td>
                    <td class="fw-bold text-success"><?= number_format($od['tong_tien'], 0, ',', '.') ?> đ</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="loadDetail(<?= $od['id'] ?>, '<?= htmlspecialchars($od['ma_hoa_don']) ?>', '<?= date('d/m/Y H:i:s', strtotime($od['created_at'])) ?>')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-end mb-0">
            <?php for ($p = 1; $p <= $total_pages; $p++):
                $params = array_merge($_GET, ['page' => $p]);
                $qstr   = http_build_query($params);
            ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= $qstr ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- ===================== MODAL CHI TIẾT ĐƠN HÀNG ===================== -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-receipt text-success me-2"></i>
                    Chi tiết đơn hàng: <span id="modal_ma_hd"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-clock me-1"></i> <span id="modal_ngay"></span>
                </p>
                <div id="modal_items_wrap">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- ===================== SCRIPTS ===================== -->
<script>
// Biểu đồ doanh thu
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const labels = <?= json_encode(array_keys($chart_data)) ?>;
    const data   = <?= json_encode(array_values($chart_data)) ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu (đ)',
                data: data,
                backgroundColor: 'rgba(16,185,129,0.25)',
                borderColor:     'rgba(16,185,129,1)',
                borderWidth: 1.5,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.y.toLocaleString('vi-VN') + ' đ'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v >= 1000000
                            ? (v/1000000).toFixed(1) + 'tr'
                            : v.toLocaleString('vi-VN')
                    }
                },
                x: { ticks: { maxRotation: 45 } }
            }
        }
    });
});

// Hiện / ẩn khoảng tùy chọn
document.querySelectorAll('[name="filter"]').forEach(btn => {
    btn.addEventListener('click', function () {
        const wrap = document.getElementById('custom_range');
        wrap.style.display = this.value === 'custom' ? '' : 'none';
    });
});

// Load chi tiết đơn hàng qua AJAX
function loadDetail(id, ma_hd, ngay) {
    document.getElementById('modal_ma_hd').textContent = ma_hd;
    document.getElementById('modal_ngay').textContent  = ngay;
    document.getElementById('modal_items_wrap').innerHTML =
        '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';

    new bootstrap.Modal(document.getElementById('orderDetailModal')).show();

    fetch('get_order_detail.php?id=' + id)
        .then(r => r.json())
        .then(items => {
            if (!items.length) {
                document.getElementById('modal_items_wrap').innerHTML =
                    '<p class="text-muted text-center">Không có chi tiết.</p>';
                return;
            }
            let total = 0;
            let html = `
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Sản phẩm</th>
                        <th class="text-center">SL</th>
                        <th class="text-end">Đơn giá</th>
                        <th class="text-end">Giảm</th>
                        <th class="text-end">Thành tiền</th>
                    </tr>
                </thead><tbody>`;
            items.forEach(it => {
                total += parseFloat(it.thanh_tien);
                html += `
                <tr>
                    <td class="fw-bold">${it.ten_san_pham}</td>
                    <td class="text-center">${it.so_luong}</td>
                    <td class="text-end">${Number(it.gia_ban).toLocaleString('vi-VN')} đ</td>
                    <td class="text-end text-danger">${it.muc_giam_gia > 0 ? '-' + it.muc_giam_gia + '%' : '—'}</td>
                    <td class="text-end fw-bold text-success">${Number(it.thanh_tien).toLocaleString('vi-VN')} đ</td>
                </tr>`;
            });
            html += `</tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="4" class="text-end">TỔNG CỘNG:</td>
                    <td class="text-end text-success fs-6">${total.toLocaleString('vi-VN')} đ</td>
                </tr>
            </tfoot></table>`;
            document.getElementById('modal_items_wrap').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('modal_items_wrap').innerHTML =
                '<p class="text-danger text-center">Lỗi tải dữ liệu.</p>';
        });
}
</script>

<?php include 'includes/footer.php'; ?>
