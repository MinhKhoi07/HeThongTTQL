<?php 
include 'connect.php'; 

// Xử lý Xóa tài khoản
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql_delete = "DELETE FROM tai_khoan WHERE id = $id";
    $conn->query($sql_delete);
    header("Location: accounts.php");
    exit();
}

// Xử lý Thêm / Sửa lưu dữ liệu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_account'])) {
    $id = $_POST['id'] ?? '';
    $ten_dang_nhap = $_POST['ten_dang_nhap'];
    $mat_khau = $_POST['mat_khau'] ? password_hash($_POST['mat_khau'], PASSWORD_DEFAULT) : '';
    $ho_ten = $_POST['ho_ten'];
    $vai_tro = $_POST['vai_tro'];
    $trang_thai = $_POST['trang_thai'];

    if ($id) {
        if ($mat_khau) {
            $sql = "UPDATE tai_khoan SET ten_dang_nhap='$ten_dang_nhap', mat_khau='$mat_khau', ho_ten='$ho_ten', vai_tro='$vai_tro', trang_thai='$trang_thai' WHERE id=$id";
        } else {
            $sql = "UPDATE tai_khoan SET ten_dang_nhap='$ten_dang_nhap', ho_ten='$ho_ten', vai_tro='$vai_tro', trang_thai='$trang_thai' WHERE id=$id";
        }
    } else {
        $sql = "INSERT INTO tai_khoan (ten_dang_nhap, mat_khau, ho_ten, vai_tro, trang_thai) VALUES ('$ten_dang_nhap', '$mat_khau', '$ho_ten', '$vai_tro', '$trang_thai')";
    }
    $conn->query($sql);
    header("Location: accounts.php");
    exit();
}

// Lấy danh sách thiết lập search
$search = $_GET['search'] ?? '';
$where = "";
if ($search) {
    $where = "WHERE ten_dang_nhap LIKE '%$search%' OR ho_ten LIKE '%$search%' OR vai_tro LIKE '%$search%'";
}

// Tính tổng và các trạng thái
$total_acc = $conn->query("SELECT COUNT(*) as c FROM tai_khoan")->fetch_assoc()['c'];
$active_acc = $conn->query("SELECT COUNT(*) as c FROM tai_khoan WHERE trang_thai=1")->fetch_assoc()['c'];
$locked_acc = $conn->query("SELECT COUNT(*) as c FROM tai_khoan WHERE trang_thai=0")->fetch_assoc()['c'];

$sql_select = "SELECT * FROM tai_khoan $where ORDER BY id DESC";
$result = $conn->query($sql_select);

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý tài khoản</h3>
        <p class="text-muted mb-0">Quản lý nhân viên và phân quyền hệ thống</p>
    </div>
    <div>
         <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#accountModal" onclick="resetForm()">
             <i class="fas fa-plus me-2"></i> Thêm tài khoản
         </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-start gap-4">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary" style="width:60px; height:60px; border-radius:50%">
                <i class="fas fa-shield-alt fa-lg"></i>
            </div>
            <div>
                <p class="text-muted mb-1">Tổng tài khoản</p>
                <h2 class="fw-bold mb-0"><?= $total_acc ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-start gap-4 border border-success border-opacity-25">
            <div class="stat-icon bg-success bg-opacity-10 text-success" style="width:60px; height:60px; border-radius:50%">
                <i class="fas fa-user-check fa-lg"></i>
            </div>
            <div>
                <p class="text-muted mb-1">Đang hoạt động</p>
                <h2 class="fw-bold mb-0"><?= $active_acc ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-start gap-4">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger" style="width:60px; height:60px; border-radius:50%">
                <i class="fas fa-user-times fa-lg"></i>
            </div>
            <div>
                <p class="text-muted mb-1">Bị khóa</p>
                <h2 class="fw-bold mb-0"><?= $locked_acc ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <form action="" method="GET" class="input-group" style="width: 400px;">
            <button type="submit" class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></button>
            <input type="text" name="search" class="form-control border-start-0 bg-light" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm theo tên, username hoặc vai trò...">
        </form>
        <div>
            <a href="accounts.php" class="btn btn-outline-secondary"><i class="fas fa-sync me-2"></i> Tải lại</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã NV</th>
                    <th>Họ và tên</th>
                    <th>Tên đăng nhập</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="fw-bold">A<?= sprintf('%02d', $row['id']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-secondary bg-opacity-25 text-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px; text-transform: uppercase;">
                                <?= substr($row['ho_ten'], 0, 1) ?>
                            </div>
                            <span class="fw-bold"><?= $row['ho_ten'] ?></span>
                        </div>
                    </td>
                    <td class="text-muted"><?= $row['ten_dang_nhap'] ?></td>
                    <td>
                        <?php if ($row['vai_tro'] == 'admin'): ?>
                            <span class="badge" style="background-color: #f3e8ff; color: #7e22ce; padding: 6px 12px; border-radius: 20px;">Quản trị viên</span>
                        <?php else: ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?= $row['vai_tro'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['trang_thai'] == 1): ?>
                            <span class="badge-active bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Hoạt động</span>
                        <?php else: ?>
                            <span class="badge-inactive bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1">Khóa</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="action-btn" onclick="editAccount(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ten_dang_nhap'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['ho_ten'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['vai_tro'], ENT_QUOTES) ?>', <?= $row['trang_thai'] ?>)">
                            <i class="far fa-edit"></i>
                        </button>
                        <a href="accounts.php?delete=<?= $row['id'] ?>" class="action-btn text-decoration-none" onclick="return confirm('Xác nhận xóa tài khoản này?');">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($result->num_rows == 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Chưa có tài khoản nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm / Sửa -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="" method="POST">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="accModalLabel">Thêm tài khoản</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="save_account" value="1">
            <input type="hidden" name="id" id="acc_id" value="">
            
            <div class="mb-3">
                <label class="form-label">Tên đăng nhập</label>
                <input type="text" name="ten_dang_nhap" id="acc_username" class="form-control" required placeholder="VD: nguyenvan_a">
            </div>
            <div class="mb-3">
                <label class="form-label">Họ và tên</label>
                <input type="text" name="ho_ten" id="acc_fullname" class="form-control" required placeholder="VD: Nguyễn Văn A">
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="mat_khau" id="acc_password" class="form-control" placeholder="Để trống nếu không đổi (khi sửa)">
            </div>
            <div class="mb-3">
                <label class="form-label">Vai trò</label>
                <select name="vai_tro" id="acc_role" class="form-select">
                    <option value="nhanvien">Nhân viên bán hàng</option>
                    <option value="admin">Quản trị viên (Admin)</option>
                    <option value="kho">Nhân viên kho</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="trang_thai" id="acc_status" class="form-select">
                    <option value="1">Hoạt động</option>
                    <option value="0">Khóa</option>
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
    document.getElementById('accModalLabel').innerText = 'Thêm tài khoản';
    document.getElementById('acc_id').value = '';
    document.getElementById('acc_username').value = '';
    document.getElementById('acc_fullname').value = '';
    document.getElementById('acc_password').required = true;
    document.getElementById('acc_role').value = 'nhanvien';
    document.getElementById('acc_status').value = '1';
}

function editAccount(id, username, fullname, role, status) {
    document.getElementById('accModalLabel').innerText = 'Sửa tài khoản';
    document.getElementById('acc_id').value = id;
    document.getElementById('acc_username').value = username;
    document.getElementById('acc_fullname').value = fullname;
    document.getElementById('acc_password').required = false; // Khi sửa không bắt buộc nhập MK
    document.getElementById('acc_role').value = role;
    document.getElementById('acc_status').value = status;
    
    var myModal = new bootstrap.Modal(document.getElementById('accountModal'));
    myModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>