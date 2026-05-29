<?php 
include 'connect.php'; 

// Xử lý Xóa sản phẩm
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql_delete = "DELETE FROM san_pham WHERE id = $id";
    $conn->query($sql_delete);
    header("Location: products.php");
    exit();
}

// Xử lý Thêm / Sửa lưu dữ liệu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $id = $_POST['id'] ?? '';
    $id_danh_muc = $_POST['id_danh_muc'];
    $ma_vach = $_POST['ma_vach'];
    $ma_sku = $_POST['ma_sku'];
    $ten_san_pham = $_POST['ten_san_pham'];
    $don_vi_tinh = $_POST['don_vi_tinh'];
    $gia_nhap = $_POST['gia_nhap'];
    $gia_ban = $_POST['gia_ban'];

    if ($id) {
        $sql = "UPDATE san_pham SET id_danh_muc='$id_danh_muc', ma_vach='$ma_vach', ma_sku='$ma_sku', ten_san_pham='$ten_san_pham', don_vi_tinh='$don_vi_tinh', gia_nhap='$gia_nhap', gia_ban='$gia_ban' WHERE id=$id";
    } else {
        $sql = "INSERT INTO san_pham (id_danh_muc, ma_vach, ma_sku, ten_san_pham, don_vi_tinh, gia_nhap, gia_ban) VALUES ('$id_danh_muc', '$ma_vach', '$ma_sku', '$ten_san_pham', '$don_vi_tinh', '$gia_nhap', '$gia_ban')";
    }
    $conn->query($sql);
    header("Location: products.php");
    exit();
}

// Lấy danh sách danh mục để đổ vào Dropdown Thêm/Sửa/Lọc
$cats = $conn->query("SELECT * FROM danh_muc WHERE trang_thai = 1");
$categories = [];
while($c = $cats->fetch_assoc()) {
    $categories[] = $c;
}

// Xử lý Tìm kiếm và Lọc
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';

$where_arr = [];
if ($search) {
    // Tìm theo tên, mã vạch hoặc mã SKU
    $where_arr[] = "(sp.ten_san_pham LIKE '%$search%' OR sp.ma_vach LIKE '%$search%' OR sp.ma_sku LIKE '%$search%')";
}
if ($cat_filter) {
    $where_arr[] = "sp.id_danh_muc = '$cat_filter'";
}
$where = count($where_arr) > 0 ? "WHERE " . implode(" AND ", $where_arr) : "";

// Truy vấn danh sách sản phẩm hiển thị trên bảng, JOIN lấy tên danh mục và SUM tồn kho
$sql_select = "SELECT sp.*, dm.ten_danh_muc, 
               IFNULL((SELECT SUM(so_luong) FROM ton_kho tk WHERE tk.id_san_pham = sp.id), 0) as ton_kho 
               FROM san_pham sp 
               LEFT JOIN danh_muc dm ON sp.id_danh_muc = dm.id 
               $where ORDER BY sp.id DESC";
$result = $conn->query($sql_select);

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý sản phẩm</h3>
        <p class="text-muted mb-0">Quản lý thông tin, giá cả và mã vạch sản phẩm</p>
    </div>
    <div>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i> Thêm sản phẩm mới
        </button>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <!-- Box Lọc & Tìm kiếm -->
        <form action="" method="GET" class="d-flex gap-2 w-100 justify-content-between">
            <div class="input-group" style="width: 350px;">
                <button type="submit" class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></button>
                <input type="text" name="search" class="form-control border-start-0 bg-light" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm theo tên hoặc mã...">
            </div>
            <div class="d-flex gap-2">
                <select name="category" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="">Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($cat_filter == $cat['id']) ? 'selected' : '' ?>><?= $cat['ten_danh_muc'] ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="products.php" class="btn btn-outline-secondary"><i class="fas fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Hình ảnh</th>
                    <th>Mã / Mã vạch</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Tồn kho</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 40px; height: 40px;">
                            <i class="fas fa-box"></i>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold"><?= $row['ma_sku'] ?></div>
                        <small class="text-muted"><?= $row['ma_vach'] ?></small>
                    </td>
                    <td class="fw-bold"><?= $row['ten_san_pham'] ?></td>
                    <td><span class="text-muted"><?= $row['ten_danh_muc'] ?? 'Không xác định' ?></span></td>
                    <td class="fw-bold text-success"><?= number_format($row['gia_ban'], 0, ',', '.') ?> <span class="text-decoration-underline">đ</span></td>
                    <td>
                        <?php if ($row['ton_kho'] > 0): ?>
                            <span class="fw-bold text-primary"><?= $row['ton_kho'] ?></span>
                        <?php else: ?>
                            <span class="fw-bold text-danger">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Gọi modal sửa bằng JS (truyền đủ tham số) -->
                        <button class="action-btn" onclick="editProduct(<?= $row['id'] ?>, <?= $row['id_danh_muc'] ?>, '<?= htmlspecialchars($row['ma_vach'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['ma_sku'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['ten_san_pham'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['don_vi_tinh'], ENT_QUOTES) ?>', <?= $row['gia_nhap'] ?>, <?= $row['gia_ban'] ?>)">
                            <i class="far fa-edit"></i>
                        </button>
                        <a href="products.php?delete=<?= $row['id'] ?>" class="action-btn text-decoration-none" onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($result->num_rows == 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="fas fa-box-open fa-3x mb-3 text-light"></i><br>
                    Chưa có sản phẩm nào.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm/Sửa Sản Phẩm -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="" method="POST">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="productModalLabel">Thêm sản phẩm mới</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="save_product" value="1">
            <input type="hidden" name="id" id="prod_id" value="">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tên sản phẩm</label>
                    <input type="text" name="ten_san_pham" id="prod_ten" class="form-control" required placeholder="VD: Nước khoáng Lavie 500ml">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Danh mục</label>
                    <select name="id_danh_muc" id="prod_danhmuc" class="form-select" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= $cat['ten_danh_muc'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mã SKU (Tự định nghĩa)</label>
                    <input type="text" name="ma_sku" id="prod_masku" class="form-control" required placeholder="VD: SP001">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mã Vạch (Barcode)</label>
                    <input type="text" name="ma_vach" id="prod_mavach" class="form-control" required placeholder="Nhập mã in trên sản phẩm">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Đơn vị tính</label>
                    <input type="text" name="don_vi_tinh" id="prod_dvt" class="form-control" required placeholder="Chai, Lốc, Thùng, Gói...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Giá nhập</label>
                    <input type="number" name="gia_nhap" id="prod_gianhap" class="form-control" required min="0" value="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Giá bán</label>
                    <input type="number" name="gia_ban" id="prod_giaban" class="form-control" required min="0" value="0">
                </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-custom">Lưu sản phẩm</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('productModalLabel').innerText = 'Thêm sản phẩm mới';
    document.getElementById('prod_id').value = '';
    document.getElementById('prod_ten').value = '';
    document.getElementById('prod_danhmuc').value = '';
    document.getElementById('prod_masku').value = '';
    document.getElementById('prod_mavach').value = '';
    document.getElementById('prod_dvt').value = '';
    document.getElementById('prod_gianhap').value = '0';
    document.getElementById('prod_giaban').value = '0';
}

function editProduct(id, id_danh_muc, ma_vach, ma_sku, ten, dvt, gia_nhap, gia_ban) {
    document.getElementById('productModalLabel').innerText = 'Sửa sản phẩm';
    document.getElementById('prod_id').value = id;
    document.getElementById('prod_ten').value = ten;
    document.getElementById('prod_danhmuc').value = id_danh_muc;
    document.getElementById('prod_masku').value = ma_sku;
    document.getElementById('prod_mavach').value = ma_vach;
    document.getElementById('prod_dvt').value = dvt;
    document.getElementById('prod_gianhap').value = gia_nhap;
    document.getElementById('prod_giaban').value = gia_ban;
    
    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>