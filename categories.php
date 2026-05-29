<?php 
include 'connect.php'; 

// Xử lý Xóa danh mục
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql_delete = "DELETE FROM danh_muc WHERE id = $id";
    $conn->query($sql_delete);
    header("Location: categories.php");
    exit();
}

// Xử lý Thêm / Sửa lưu dữ liệu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    $id = $_POST['id'] ?? '';
    $ma_danh_muc = $_POST['ma_danh_muc'];
    $ten_danh_muc = $_POST['ten_danh_muc'];
    $trang_thai = $_POST['trang_thai'];

    if ($id) {
        $sql = "UPDATE danh_muc SET ma_danh_muc='$ma_danh_muc', ten_danh_muc='$ten_danh_muc', trang_thai='$trang_thai' WHERE id=$id";
    } else {
        $sql = "INSERT INTO danh_muc (ma_danh_muc, ten_danh_muc, trang_thai) VALUES ('$ma_danh_muc', '$ten_danh_muc', '$trang_thai')";
    }
    $conn->query($sql);
    header("Location: categories.php");
    exit();
}

// Lấy danh sách danh mục
$search = $_GET['search'] ?? '';
$where = "";
if ($search) {
    $where = "WHERE ten_danh_muc LIKE '%$search%' OR ma_danh_muc LIKE '%$search%'";
}
$sql_select = "SELECT d.*, (SELECT COUNT(*) FROM san_pham s WHERE s.id_danh_muc = d.id) as so_luong_sp FROM danh_muc d $where ORDER BY id DESC";
$result = $conn->query($sql_select);

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý danh mục</h3>
        <p class="text-muted mb-0">Quản lý và phân loại các nhóm sản phẩm</p>
    </div>
    <div>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i> Thêm danh mục mới
        </button>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <form action="" method="GET" class="input-group" style="width: 300px;">
            <button type="submit" class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></button>
            <input type="text" name="search" class="form-control border-start-0 bg-light" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm danh mục...">
        </form>
        <div>
            <a href="categories.php" class="btn btn-outline-secondary"><i class="fas fa-sync me-2"></i> Tải lại</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã DM</th>
                    <th>Tên danh mục</th>
                    <th>Số lượng SP</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="fw-bold text-dark"><?= $row['ma_danh_muc'] ?></td>
                    <td class="fw-bold text-dark"><?= $row['ten_danh_muc'] ?></td>
                    <td><?= $row['so_luong_sp'] ?></td>
                    <td>
                        <?php if ($row['trang_thai'] == 1): ?>
                            <span class="badge bg-success bg-opacity-25 text-success px-3 py-1 rounded-pill">Hoạt động</span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-25 text-secondary px-3 py-1 rounded-pill">Ngừng kinh doanh</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="action-btn" onclick="editCategory(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ma_danh_muc'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['ten_danh_muc'], ENT_QUOTES) ?>', <?= $row['trang_thai'] ?>)">
                            <i class="far fa-edit"></i>
                        </button>
                        <a href="categories.php?delete=<?= $row['id'] ?>" class="action-btn text-decoration-none" onclick="return confirm('Bạn có chắc muốn xóa danh mục này?');">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($result->num_rows == 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Chưa có dữ liệu danh mục nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="" method="POST">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="categoryModalLabel">Thêm danh mục mới</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="save_category" value="1">
            <input type="hidden" name="id" id="cat_id" value="">
            
            <div class="mb-3">
                <label class="form-label">Mã danh mục</label>
                <input type="text" name="ma_danh_muc" id="cat_ma" class="form-control" required placeholder="VD: C01">
            </div>
            <div class="mb-3">
                <label class="form-label">Tên danh mục</label>
                <input type="text" name="ten_danh_muc" id="cat_ten" class="form-control" required placeholder="VD: Đồ uống">
            </div>
            <div class="mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="trang_thai" id="cat_trangthai" class="form-select">
                    <option value="1">Hoạt động</option>
                    <option value="0">Ngừng kinh doanh</option>
                </select>
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
    document.getElementById('categoryModalLabel').innerText = 'Thêm danh mục mới';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_ma').value = '';
    document.getElementById('cat_ten').value = '';
    document.getElementById('cat_trangthai').value = '1';
}

function editCategory(id, ma, ten, trang_thai) {
    document.getElementById('categoryModalLabel').innerText = 'Sửa danh mục';
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_ma').value = ma;
    document.getElementById('cat_ten').value = ten;
    document.getElementById('cat_trangthai').value = trang_thai;
    
    var myModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    myModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>