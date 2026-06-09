<?php 
include 'connect.php'; 

// Xử lý Xóa khuyến mãi
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM khuyen_mai WHERE id = $id");
    header("Location: promotions.php");
    exit();
}

// Xử lý Thêm / Sửa khuyến mãi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_promotion'])) {
    $id = $_POST['id'] ?? '';
    $ma_km = $_POST['ma_km'];
    $ten_chuong_trinh = $_POST['ten_chuong_trinh'];
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $muc_giam = $_POST['muc_giam'];
    $san_phams = $_POST['san_phams'] ?? [];

    if ($id) {
        // Cập nhật
        $sql = "UPDATE khuyen_mai SET ma_km='$ma_km', ten_chuong_trinh='$ten_chuong_trinh', 
                ngay_bat_dau='$ngay_bat_dau', ngay_ket_thuc='$ngay_ket_thuc', muc_giam='$muc_giam' 
                WHERE id=$id";
        $conn->query($sql);
        
        // Xóa sản phẩm cũ và thêm lại
        $conn->query("DELETE FROM chi_tiet_khuyen_mai WHERE id_khuyen_mai = $id");
        foreach ($san_phams as $id_sp) {
            $conn->query("INSERT INTO chi_tiet_khuyen_mai (id_khuyen_mai, id_san_pham) VALUES ($id, $id_sp)");
        }
    } else {
        // Thêm mới
        $sql = "INSERT INTO khuyen_mai (ma_km, ten_chuong_trinh, ngay_bat_dau, ngay_ket_thuc, muc_giam) 
                VALUES ('$ma_km', '$ten_chuong_trinh', '$ngay_bat_dau', '$ngay_ket_thuc', '$muc_giam')";
        $conn->query($sql);
        $new_id = $conn->insert_id;
        
        // Thêm sản phẩm áp dụng
        foreach ($san_phams as $id_sp) {
            $conn->query("INSERT INTO chi_tiet_khuyen_mai (id_khuyen_mai, id_san_pham) VALUES ($new_id, $id_sp)");
        }
    }
    header("Location: promotions.php");
    exit();
}

// Lấy danh sách sản phẩm
$products = $conn->query("SELECT id, ten_san_pham, ma_sku FROM san_pham ORDER BY ten_san_pham");

// Lấy danh sách khuyến mãi
$sql_select = "SELECT km.*, 
               COUNT(DISTINCT ctkm.id_san_pham) as so_san_pham
               FROM khuyen_mai km
               LEFT JOIN chi_tiet_khuyen_mai ctkm ON km.id = ctkm.id_khuyen_mai
               GROUP BY km.id
               ORDER BY km.id DESC";
$result = $conn->query($sql_select);

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý khuyến mãi</h3>
        <p class="text-muted mb-0">Tạo và quản lý các chương trình khuyến mãi, giảm giá</p>
    </div>
    <div>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#promotionModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i> Tạo khuyến mãi mới
        </button>
    </div>
</div>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã KM</th>
                    <th>Tên chương trình</th>
                    <th>Thời gian</th>
                    <th>Mức giảm</th>
                    <th>Số SP áp dụng</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): 
                    $now = date('Y-m-d H:i:s');
                    $status = '';
                    $badge = '';
                    if ($now < $row['ngay_bat_dau']) {
                        $status = 'Sắp diễn ra';
                        $badge = 'bg-info';
                    } elseif ($now > $row['ngay_ket_thuc']) {
                        $status = 'Đã kết thúc';
                        $badge = 'bg-secondary';
                    } else {
                        $status = 'Đang diễn ra';
                        $badge = 'bg-success';
                    }
                ?>
                <tr>
                    <td class="fw-bold"><?= $row['ma_km'] ?></td>
                    <td><?= $row['ten_chuong_trinh'] ?></td>
                    <td>
                        <small class="d-block text-muted">Từ: <?= date('d/m/Y H:i', strtotime($row['ngay_bat_dau'])) ?></small>
                        <small class="d-block text-muted">Đến: <?= date('d/m/Y H:i', strtotime($row['ngay_ket_thuc'])) ?></small>
                    </td>
                    <td><span class="badge bg-danger fs-6"><?= $row['muc_giam'] ?>%</span></td>
                    <td class="text-center"><?= $row['so_san_pham'] ?></td>
                    <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
                    <td>
                        <button class="action-btn" onclick="editPromotion(<?= $row['id'] ?>)">
                            <i class="far fa-edit"></i>
                        </button>
                        <a href="promotions.php?delete=<?= $row['id'] ?>" class="action-btn text-decoration-none" onclick="return confirm('Bạn có chắc muốn xóa khuyến mãi này?');">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($result->num_rows == 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="fas fa-tags fa-3x mb-3 text-light"></i><br>
                    Chưa có chương trình khuyến mãi nào.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm/Sửa Khuyến mãi -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="" method="POST">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="promotionModalLabel">Tạo khuyến mãi mới</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="save_promotion" value="1">
            <input type="hidden" name="id" id="promo_id" value="">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mã khuyến mãi</label>
                    <input type="text" name="ma_km" id="promo_ma" class="form-control" required placeholder="VD: KM001">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mức giảm giá (%)</label>
                    <input type="number" name="muc_giam" id="promo_giam" class="form-control" required min="0" max="100" step="0.01" placeholder="VD: 10.5">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Tên chương trình</label>
                <input type="text" name="ten_chuong_trinh" id="promo_ten" class="form-control" required placeholder="VD: Giảm giá cuối tuần">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="datetime-local" name="ngay_bat_dau" id="promo_batdau" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ngày kết thúc</label>
                    <input type="datetime-local" name="ngay_ket_thuc" id="promo_ketthuc" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Chọn sản phẩm áp dụng</label>
                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                    <?php while ($prod = $products->fetch_assoc()): ?>
                    <div class="form-check">
                        <input class="form-check-input product-checkbox" type="checkbox" name="san_phams[]" value="<?= $prod['id'] ?>" id="prod_<?= $prod['id'] ?>">
                        <label class="form-check-label" for="prod_<?= $prod['id'] ?>">
                            <?= $prod['ten_san_pham'] ?> <small class="text-muted">(<?= $prod['ma_sku'] ?>)</small>
                        </label>
                    </div>
                    <?php endwhile; ?>
                </div>
                <small class="text-muted">Chọn các sản phẩm được áp dụng khuyến mãi</small>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-custom">Lưu khuyến mãi</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('promotionModalLabel').innerText = 'Tạo khuyến mãi mới';
    document.getElementById('promo_id').value = '';
    document.getElementById('promo_ma').value = '';
    document.getElementById('promo_ten').value = '';
    document.getElementById('promo_batdau').value = '';
    document.getElementById('promo_ketthuc').value = '';
    document.getElementById('promo_giam').value = '';
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
}

function editPromotion(id) {
    // Fetch data via AJAX
    fetch('get_promotion.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('promotionModalLabel').innerText = 'Sửa khuyến mãi';
            document.getElementById('promo_id').value = data.id;
            document.getElementById('promo_ma').value = data.ma_km;
            document.getElementById('promo_ten').value = data.ten_chuong_trinh;
            document.getElementById('promo_batdau').value = data.ngay_bat_dau.replace(' ', 'T');
            document.getElementById('promo_ketthuc').value = data.ngay_ket_thuc.replace(' ', 'T');
            document.getElementById('promo_giam').value = data.muc_giam;
            
            // Uncheck all first
            document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
            
            // Check selected products
            data.san_phams.forEach(sp_id => {
                const checkbox = document.getElementById('prod_' + sp_id);
                if (checkbox) checkbox.checked = true;
            });
            
            var myModal = new bootstrap.Modal(document.getElementById('promotionModal'));
            myModal.show();
        });
}
</script>

<?php include 'includes/footer.php'; ?>
