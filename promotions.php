<?php 
include 'connect.php'; 

// Migration (tự động thêm cột nếu chưa có)
try {
    $conn->query("ALTER TABLE khuyen_mai ADD COLUMN loai_ap_dung VARCHAR(20) DEFAULT 'tat_ca'");
    $conn->query("ALTER TABLE khuyen_mai ADD COLUMN gia_tri_ap_dung VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {}

// Xử lý Xóa khuyến mãi
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql_delete = "DELETE FROM khuyen_mai WHERE id = $id";
    $conn->query($sql_delete);
    header("Location: promotions.php");
    exit();
}

// Xử lý Thêm / Sửa lưu dữ liệu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_promotion'])) {
    $id = $_POST['id'] ?? '';
    $ma_km = $_POST['ma_km'];
    $ten_chuong_trinh = $_POST['ten_chuong_trinh'];
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $muc_giam = $_POST['muc_giam'];
    
    $loai_ap_dung = $_POST['loai_ap_dung'] ?? 'tat_ca';
    $gia_tri_ap_dung = '';
    
    if ($loai_ap_dung == 'danh_muc') {
        $gia_tri_ap_dung = $_POST['id_danh_muc'] ?? '';
    } elseif ($loai_ap_dung == 'san_pham') {
        $id_san_pham_arr = $_POST['id_san_pham'] ?? [];
        $gia_tri_ap_dung = implode(',', $id_san_pham_arr);
    }

    if ($id) {
        $sql = "UPDATE khuyen_mai SET ma_km='$ma_km', ten_chuong_trinh='$ten_chuong_trinh', ngay_bat_dau='$ngay_bat_dau', ngay_ket_thuc='$ngay_ket_thuc', muc_giam='$muc_giam', loai_ap_dung='$loai_ap_dung', gia_tri_ap_dung='$gia_tri_ap_dung' WHERE id=$id";
    } else {
        $sql = "INSERT INTO khuyen_mai (ma_km, ten_chuong_trinh, ngay_bat_dau, ngay_ket_thuc, muc_giam, loai_ap_dung, gia_tri_ap_dung) VALUES ('$ma_km', '$ten_chuong_trinh', '$ngay_bat_dau', '$ngay_ket_thuc', '$muc_giam', '$loai_ap_dung', '$gia_tri_ap_dung')";
    }
    $conn->query($sql);

    // Lưu vào bảng chi_tiet_khuyen_mai
    $km_id = $id ? $id : $conn->insert_id;
    $conn->query("DELETE FROM chi_tiet_khuyen_mai WHERE id_khuyen_mai = $km_id");
    
    if ($loai_ap_dung == 'san_pham' && !empty($id_san_pham_arr)) {
        foreach ($id_san_pham_arr as $sp_id) {
            $conn->query("INSERT INTO chi_tiet_khuyen_mai (id_khuyen_mai, id_san_pham) VALUES ($km_id, $sp_id)");
        }
    } elseif ($loai_ap_dung == 'danh_muc' && !empty($gia_tri_ap_dung)) {
        // Tự động gán cho tất cả sản phẩm thuộc danh mục
        $sp_list = $conn->query("SELECT id FROM san_pham WHERE id_danh_muc = '$gia_tri_ap_dung'");
        while($sp = $sp_list->fetch_assoc()) {
            $sp_id = $sp['id'];
            $conn->query("INSERT INTO chi_tiet_khuyen_mai (id_khuyen_mai, id_san_pham) VALUES ($km_id, $sp_id)");
        }
    }

    header("Location: promotions.php");
    exit();
}

// Lấy danh sách danh mục & sản phẩm
$categories = $conn->query("SELECT * FROM danh_muc WHERE trang_thai = 1")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT * FROM san_pham")->fetch_all(MYSQLI_ASSOC);

// Lấy danh sách khuyến mãi
$search = $_GET['search'] ?? '';
$where = "";
if ($search) {
    $where = "WHERE ten_chuong_trinh LIKE '%$search%' OR ma_km LIKE '%$search%'";
}
$sql_select = "SELECT * FROM khuyen_mai $where ORDER BY id DESC";
$result = $conn->query($sql_select);

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý khuyến mãi</h3>
        <p class="text-muted mb-0">Quản lý các chương trình giảm giá, khuyến mãi</p>
    </div>
    <div>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#promotionModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i> Thêm khuyến mãi mới
        </button>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <form action="" method="GET" class="input-group" style="width: 300px;">
            <button type="submit" class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></button>
            <input type="text" name="search" class="form-control border-start-0 bg-light" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm khuyến mãi...">
        </form>
        <div>
            <a href="promotions.php" class="btn btn-outline-secondary"><i class="fas fa-sync me-2"></i> Tải lại</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã KM</th>
                    <th>Tên chương trình</th>
                    <th>Áp dụng cho</th>
                    <th>Thời gian áp dụng</th>
                    <th>Mức giảm (%)</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $now = date('Y-m-d H:i:s');
                while ($row = $result->fetch_assoc()): 
                    // Xác định trạng thái của KM
                    if ($now < $row['ngay_bat_dau']) {
                        $status_badge = '<span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill">Sắp diễn ra</span>';
                    } elseif ($now > $row['ngay_ket_thuc']) {
                        $status_badge = '<span class="badge bg-secondary bg-opacity-25 text-secondary px-3 py-1 rounded-pill">Đã kết thúc</span>';
                    } else {
                        $status_badge = '<span class="badge bg-success bg-opacity-25 text-success px-3 py-1 rounded-pill">Đang diễn ra</span>';
                    }
                    
                    // Xác định đối tượng áp dụng
                    $ap_dung_text = 'Tất cả sản phẩm';
                    if ($row['loai_ap_dung'] == 'danh_muc') {
                        $dm_id = $row['gia_tri_ap_dung'];
                        $dm = $conn->query("SELECT ten_danh_muc FROM danh_muc WHERE id='$dm_id'")->fetch_assoc();
                        $ap_dung_text = 'Danh mục: ' . ($dm['ten_danh_muc'] ?? 'Không rõ');
                    } elseif ($row['loai_ap_dung'] == 'san_pham') {
                        $sp_count = $conn->query("SELECT COUNT(*) as c FROM chi_tiet_khuyen_mai WHERE id_khuyen_mai='{$row['id']}'")->fetch_assoc()['c'];
                        $ap_dung_text = $sp_count . ' sản phẩm cụ thể';
                    }
                ?>
                <tr>
                    <td class="fw-bold text-dark"><?= $row['ma_km'] ?></td>
                    <td class="fw-bold text-dark"><?= $row['ten_chuong_trinh'] ?></td>
                    <td class="text-primary"><?= $ap_dung_text ?></td>
                    <td>
                        <small>Từ: <?= date('d/m/Y H:i', strtotime($row['ngay_bat_dau'])) ?></small><br>
                        <small>Đến: <?= date('d/m/Y H:i', strtotime($row['ngay_ket_thuc'])) ?></small>
                    </td>
                    <td class="text-danger fw-bold"><?= $row['muc_giam'] ?>%</td>
                    <td><?= $status_badge ?></td>
                    <td>
                        <a href="promotions.php?delete=<?= $row['id'] ?>" class="action-btn text-decoration-none" onclick="return confirm('Bạn có chắc muốn xóa khuyến mãi này?');">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($result->num_rows == 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">Chưa có dữ liệu khuyến mãi nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="" method="POST">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="promotionModalLabel">Thêm khuyến mãi mới</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="save_promotion" value="1">
            <input type="hidden" name="id" id="km_id" value="">
            
            <div class="mb-3">
                <label class="form-label">Mã khuyến mãi</label>
                <input type="text" name="ma_km" id="km_ma" class="form-control" required placeholder="VD: KM01">
            </div>
            <div class="mb-3">
                <label class="form-label">Tên chương trình</label>
                <input type="text" name="ten_chuong_trinh" id="km_ten" class="form-control" required placeholder="VD: Giảm giá mùa hè">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Phạm vi áp dụng</label>
                <select name="loai_ap_dung" id="km_loai" class="form-select" onchange="toggleScope()">
                    <option value="tat_ca">Tất cả sản phẩm</option>
                    <option value="danh_muc">Theo danh mục cụ thể</option>
                    <option value="san_pham">Theo sản phẩm chọn lọc</option>
                </select>
            </div>
            
            <div class="mb-3" id="box_danh_muc" style="display: none;">
                <label class="form-label">Chọn danh mục</label>
                <select name="id_danh_muc" class="form-select">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['ten_danh_muc'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3" id="box_san_pham" style="display: none;">
                <label class="form-label">Chọn sản phẩm (nhấn Ctrl/Command để chọn nhiều)</label>
                <select name="id_san_pham[]" class="form-select" multiple size="4">
                    <?php foreach ($products as $sp): ?>
                        <option value="<?= $sp['id'] ?>"><?= $sp['ma_sku'] ?> - <?= $sp['ten_san_pham'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Thời gian bắt đầu</label>
                    <input type="datetime-local" name="ngay_bat_dau" id="km_batdau" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Thời gian kết thúc</label>
                    <input type="datetime-local" name="ngay_ket_thuc" id="km_ketthuc" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Mức giảm giá (%)</label>
                <div class="input-group">
                    <input type="number" step="0.01" max="100" min="0" name="muc_giam" id="km_giam" class="form-control" required placeholder="VD: 10">
                    <span class="input-group-text">%</span>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-custom">Lưu</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('promotionModalLabel').innerText = 'Thêm khuyến mãi mới';
    document.getElementById('km_id').value = '';
    document.getElementById('km_ma').value = '';
    document.getElementById('km_ten').value = '';
    document.getElementById('km_batdau').value = '';
    document.getElementById('km_ketthuc').value = '';
    document.getElementById('km_giam').value = '';
    document.getElementById('km_loai').value = 'tat_ca';
    toggleScope();
}

function toggleScope() {
    var loai = document.getElementById('km_loai').value;
    document.getElementById('box_danh_muc').style.display = (loai === 'danh_muc') ? 'block' : 'none';
    document.getElementById('box_san_pham').style.display = (loai === 'san_pham') ? 'block' : 'none';
}
</script>

<?php include 'includes/footer.php'; ?>