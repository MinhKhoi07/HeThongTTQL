<?php 
include 'connect.php'; 

// Cập nhật tồn kho thủ công (Điều chỉnh)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    $id_san_pham = $_POST['id_san_pham'];
    $so_luong_moi = (int)$_POST['so_luong'];
    
    // Kiểm tra xem đã có dòng tồn kho cho sản phẩm này chưa (chữ ký đơn giản kho_hang = 1)
    $check = $conn->query("SELECT id FROM ton_kho WHERE id_san_pham = $id_san_pham");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE ton_kho SET so_luong = $so_luong_moi WHERE id_san_pham = $id_san_pham");
    } else {
        // Tạm thời gán kho_hang = 1 (nếu chưa có kho)
        $conn->query("INSERT INTO ton_kho (id_san_pham, id_kho_hang, so_luong) VALUES ($id_san_pham, 1, $so_luong_moi)");
    }
    header("Location: inventory.php");
    exit();
}

// Tìm kiếm
$search = $_GET['search'] ?? '';
$where = "";
if ($search) {
    $where = "WHERE sp.ten_san_pham LIKE '%$search%' OR sp.ma_vach LIKE '%$search%' OR sp.ma_sku LIKE '%$search%'";
}

// Báo cáo widget
$total_sp = $conn->query("SELECT COUNT(*) as c FROM san_pham")->fetch_assoc()['c'];

$sql_tonkho = "
    SELECT sp.id, sp.ma_vach, sp.ma_sku, sp.ten_san_pham, sp.gia_ban, sp.ton_toi_thieu, dm.ten_danh_muc,
    IFNULL((SELECT SUM(so_luong) FROM ton_kho tk WHERE tk.id_san_pham = sp.id), 0) as ton_kho
    FROM san_pham sp
    LEFT JOIN danh_muc dm ON sp.id_danh_muc = dm.id
    $where ORDER BY sp.id DESC
";
$result = $conn->query($sql_tonkho);

$sap_het = 0;
// Lấy toàn bộ mảng dữ liệu để dùng nhiều lần
$products = [];
while ($row = $result->fetch_assoc()) {
    if ($row['ton_kho'] <= $row['ton_toi_thieu']) {
        $sap_het++;
    }
    $products[] = $row;
}
$tinh_trang = ($sap_het / max($total_sp, 1) > 0.5) ? 'Cảnh báo' : 'Tốt';

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Kiểm tra tồn kho</h3>
        <p class="text-muted mb-0">Quản lý và theo dõi số lượng hàng hóa trong kho</p>
    </div>
    <div>
        <button class="btn btn-outline-secondary me-2"><i class="fas fa-file-export me-2"></i> Xuất báo cáo</button>
        <a href="products.php" class="btn btn-custom"><i class="fas fa-box me-2"></i> Quản lý Sản phẩm</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-between border border-primary border-opacity-25">
            <div>
                <p class="text-muted mb-1">Tổng sản phẩm</p>
                <h3 class="fw-bold mb-0"><?= $total_sp ?></h3>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fas fa-filter"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-between border <?= $sap_het > 0 ? 'border-danger' : 'border-success' ?> border-opacity-25">
            <div>
                <p class="text-muted mb-1">Sắp hết hàng</p>
                <h3 class="fw-bold mb-0 <?= $sap_het > 0 ? 'text-danger' : 'text-success' ?>"><?= $sap_het ?></h3>
            </div>
            <div class="stat-icon <?= $sap_het > 0 ? 'bg-danger text-danger' : 'bg-success text-success' ?> bg-opacity-10">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-between border <?= $tinh_trang == 'Tốt' ? 'border-success' : 'border-warning' ?> border-opacity-25 shadow-sm">
            <div>
                <p class="text-muted mb-1">Tình trạng kho</p>
                <h3 class="fw-bold mb-0 <?= $tinh_trang == 'Tốt' ? 'text-success' : 'text-warning' ?>"><?= $tinh_trang ?></h3>
            </div>
            <div class="stat-icon <?= $tinh_trang == 'Tốt' ? 'bg-success text-success' : 'bg-warning text-warning' ?> bg-opacity-10">
                <i class="fas <?= $tinh_trang == 'Tốt' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            </div>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <form action="" method="GET" class="input-group" style="width: 400px;">
            <button type="submit" class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></button>
            <input type="text" name="search" class="form-control border-start-0 bg-light" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên hoặc mã vạch...">
        </form>
        <div>
           <a href="inventory.php" class="btn btn-outline-secondary"><i class="fas fa-sync"></i> Tải lại</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã SP / Barcode</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                    <th>Chỉnh sửa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $row): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= $row['ma_sku'] ?></div>
                        <small class="text-muted"><?= $row['ma_vach'] ?></small>
                    </td>
                    <td>
                        <i class="fas fa-box text-muted me-2 border rounded p-2" style="background:#f8f9fa;"></i> 
                        <span class="fw-bold"><?= $row['ten_san_pham'] ?></span>
                    </td>
                    <td><span class="badge bg-light text-secondary border"><?= $row['ten_danh_muc'] ?? 'Không rõ' ?></span></td>
                    <td class="fw-bold"><?= number_format($row['gia_ban'], 0, ',', '.') ?> <span class="text-decoration-underline">đ</span></td>
                    <td class="fw-bold fs-5 <?= $row['ton_kho'] <= $row['ton_toi_thieu'] ? 'text-danger' : 'text-success' ?>">
                        <?= $row['ton_kho'] ?>
                    </td>
                    <td>
                        <?php if ($row['ton_kho'] <= 0): ?>
                            <span class="badge-inactive bg-danger bg-opacity-10 text-danger px-3 py-1 rounded-pill">Hết hàng</span>
                        <?php elseif ($row['ton_kho'] <= $row['ton_toi_thieu']): ?>
                            <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill">Cảnh báo</span>
                        <?php else: ?>
                            <span class="badge-active">Bình thường</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="adjustStock(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ten_san_pham'], ENT_QUOTES) ?>', <?= $row['ton_kho'] ?>)">
                            <i class="fas fa-edit"></i> SL
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($products) == 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có sản phẩm nào để hiển thị tồn kho.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Điều Chỉnh Tồn Kho -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form action="" method="POST">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Điều chỉnh SL Tồn kho</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="adjust_stock" value="1">
            <input type="hidden" name="id_san_pham" id="stock_sp_id">
            
            <div class="mb-3 text-center">
                <span id="stock_sp_name" class="fw-bold text-success"></span>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted">Số lượng thực tế trong kho</label>
                <input type="number" name="so_luong" id="stock_qty" class="form-control form-control-lg text-center fw-bold" required min="0">
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-custom w-100">Cập nhật</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function adjustStock(id, name, currentQty) {
    document.getElementById('stock_sp_id').value = id;
    document.getElementById('stock_sp_name').innerText = name;
    document.getElementById('stock_qty').value = currentQty;
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>