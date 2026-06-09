<?php
include 'connect.php';

// -------------------------------------------------------
// Xử lý Xóa
// -------------------------------------------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Kiểm tra đang được dùng trong phiếu nhập không
    $used = $conn->query("SELECT COUNT(*) as c FROM phieu_nhap WHERE id_nha_cung_cap = $id")->fetch_assoc()['c'];
    if ($used > 0) {
        $error_msg = "Không thể xóa! Nhà cung cấp này đang được dùng trong $used phiếu nhập.";
    } else {
        $conn->query("DELETE FROM nha_cung_cap WHERE id = $id");
        header("Location: suppliers.php?msg=deleted");
        exit();
    }
}

// -------------------------------------------------------
// Xử lý Thêm / Sửa
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_supplier'])) {
    $id          = (int)($_POST['id'] ?? 0);
    $ma_ncc      = trim($_POST['ma_ncc']);
    $ten_ncc     = trim($_POST['ten_ncc']);
    $so_dt       = trim($_POST['so_dien_thoai'] ?? '');
    $email       = trim($_POST['email']         ?? '');
    $dia_chi     = trim($_POST['dia_chi']        ?? '');
    $ma_so_thue  = trim($_POST['ma_so_thue']    ?? '');
    $ghi_chu     = trim($_POST['ghi_chu']        ?? '');

    // Validate bắt buộc
    if ($ma_ncc === '' || $ten_ncc === '') {
        $error_msg = "Mã NCC và Tên nhà cung cấp không được để trống!";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE nha_cung_cap SET ma_ncc=?, ten_ncc=?, so_dien_thoai=?, email=?, dia_chi=?, ma_so_thue=?, ghi_chu=? WHERE id=?");
            $stmt->bind_param("sssssssi", $ma_ncc, $ten_ncc, $so_dt, $email, $dia_chi, $ma_so_thue, $ghi_chu, $id);
        } else {
            // Kiểm tra trùng mã
            $dup = $conn->query("SELECT id FROM nha_cung_cap WHERE ma_ncc = '" . $conn->real_escape_string($ma_ncc) . "'")->num_rows;
            if ($dup > 0) {
                $error_msg = "Mã nhà cung cấp '$ma_ncc' đã tồn tại!";
                goto render;
            }
            $stmt = $conn->prepare("INSERT INTO nha_cung_cap (ma_ncc, ten_ncc, so_dien_thoai, email, dia_chi, ma_so_thue, ghi_chu) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $ma_ncc, $ten_ncc, $so_dt, $email, $dia_chi, $ma_so_thue, $ghi_chu);
        }
        $stmt->execute();
        header("Location: suppliers.php?msg=" . ($id > 0 ? 'updated' : 'added'));
        exit();
    }
}

render:
// -------------------------------------------------------
// Lấy dữ liệu hiển thị
// -------------------------------------------------------
$search = $_GET['search'] ?? '';
$where  = '';
if ($search !== '') {
    $s     = $conn->real_escape_string($search);
    $where = "WHERE ma_ncc LIKE '%$s%' OR ten_ncc LIKE '%$s%' OR so_dien_thoai LIKE '%$s%'";
}

$result = $conn->query("
    SELECT ncc.*,
           (SELECT COUNT(*) FROM phieu_nhap pn WHERE pn.id_nha_cung_cap = ncc.id) AS so_phieu
    FROM nha_cung_cap ncc
    $where
    ORDER BY ncc.id DESC
");
$suppliers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$total_ncc  = $conn->query("SELECT COUNT(*) as c FROM nha_cung_cap")->fetch_assoc()['c'];
$total_nhap = $conn->query("SELECT COUNT(*) as c FROM phieu_nhap")->fetch_assoc()['c'];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý nhà cung cấp</h3>
        <p class="text-muted mb-0">Quản lý thông tin các nhà cung cấp hàng hóa</p>
    </div>
    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetForm()">
        <i class="fas fa-plus me-2"></i> Thêm nhà cung cấp
    </button>
</div>

<!-- Thông báo -->
<?php if (isset($error_msg)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php
$flash_msgs = ['added' => ['success','Thêm nhà cung cấp thành công!'], 'updated' => ['success','Cập nhật thành công!'], 'deleted' => ['warning','Đã xóa nhà cung cấp.']];
if (isset($_GET['msg']) && isset($flash_msgs[$_GET['msg']])): [$type, $text] = $flash_msgs[$_GET['msg']]; ?>
<div class="alert alert-<?= $type ?> alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><?= $text ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Widget thống kê -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0 border-start border-primary border-4">
            <p class="text-muted mb-1">Tổng nhà cung cấp</p>
            <h2 class="fw-bold mb-0"><?= $total_ncc ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 shadow-sm border-0 border-start border-success border-4">
            <p class="text-muted mb-1">Tổng phiếu nhập</p>
            <h2 class="fw-bold mb-0"><?= $total_nhap ?></h2>
        </div>
    </div>
</div>

<!-- Bảng danh sách -->
<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <form action="" method="GET" class="input-group" style="width:360px">
            <button type="submit" class="input-group-text bg-light border-end-0">
                <i class="fas fa-search text-muted"></i>
            </button>
            <input type="text" name="search" class="form-control border-start-0 bg-light"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Tìm theo mã, tên, số điện thoại...">
        </form>
        <a href="suppliers.php" class="btn btn-outline-secondary">
            <i class="fas fa-sync me-1"></i> Tải lại
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mã NCC</th>
                    <th>Tên nhà cung cấp</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Địa chỉ</th>
                    <th>Mã số thuế</th>
                    <th>Phiếu nhập</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $row): ?>
                <tr>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['ma_ncc']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($row['ten_ncc']) ?></td>
                    <td><?= htmlspecialchars($row['so_dien_thoai'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                    <td class="text-muted" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        <?= htmlspecialchars($row['dia_chi'] ?? '—') ?>
                    </td>
                    <td><?= htmlspecialchars($row['ma_so_thue'] ?? '—') ?></td>
                    <td>
                        <?php if ($row['so_phieu'] > 0): ?>
                            <a href="imports.php" class="badge bg-success bg-opacity-15 text-success text-decoration-none px-2 py-1 rounded-pill">
                                <?= $row['so_phieu'] ?> phiếu
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">0 phiếu</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="action-btn"
                                onclick="editSupplier(<?= htmlspecialchars(json_encode($row, JSON_HEX_QUOT | JSON_HEX_APOS), ENT_QUOTES) ?>)">
                            <i class="far fa-edit"></i>
                        </button>
                        <a href="suppliers.php?delete=<?= $row['id'] ?>"
                           class="action-btn text-decoration-none"
                           onclick="return confirm('Xác nhận xóa nhà cung cấp \'<?= htmlspecialchars(addslashes($row['ten_ncc'])) ?>\'?')">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-truck fa-3x mb-3 opacity-25 d-block"></i>
                        Chưa có nhà cung cấp nào.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm / Sửa -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="supplierModalTitle">Thêm nhà cung cấp mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="save_supplier" value="1">
                <input type="hidden" name="id" id="sup_id" value="0">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mã NCC <span class="text-danger">*</span></label>
                        <input type="text" name="ma_ncc" id="sup_ma" class="form-control" required
                               placeholder="VD: NCC001">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Tên nhà cung cấp <span class="text-danger">*</span></label>
                        <input type="text" name="ten_ncc" id="sup_ten" class="form-control" required
                               placeholder="VD: Công ty TNHH Thực phẩm ABC">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="so_dien_thoai" id="sup_sdt" class="form-control"
                               placeholder="VD: 0901234567">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="sup_email" class="form-control"
                               placeholder="VD: contact@abc.com">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mã số thuế</label>
                        <input type="text" name="ma_so_thue" id="sup_mst" class="form-control"
                               placeholder="VD: 0123456789">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="dia_chi" id="sup_dc" class="form-control"
                           placeholder="VD: 123 Nguyễn Văn A, Q.1, TP.HCM">
                </div>

                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="ghi_chu" id="sup_ghichu" class="form-control" rows="2"
                              placeholder="Ghi chú thêm về nhà cung cấp..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-custom">
                    <i class="fas fa-save me-1"></i> Lưu
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('supplierModalTitle').innerText = 'Thêm nhà cung cấp mới';
    document.getElementById('sup_id').value     = '0';
    document.getElementById('sup_ma').value     = '';
    document.getElementById('sup_ten').value    = '';
    document.getElementById('sup_sdt').value    = '';
    document.getElementById('sup_email').value  = '';
    document.getElementById('sup_mst').value    = '';
    document.getElementById('sup_dc').value     = '';
    document.getElementById('sup_ghichu').value = '';
    document.getElementById('sup_ma').readOnly  = false;
}

function editSupplier(data) {
    document.getElementById('supplierModalTitle').innerText = 'Sửa nhà cung cấp';
    document.getElementById('sup_id').value     = data.id;
    document.getElementById('sup_ma').value     = data.ma_ncc;
    document.getElementById('sup_ten').value    = data.ten_ncc;
    document.getElementById('sup_sdt').value    = data.so_dien_thoai  ?? '';
    document.getElementById('sup_email').value  = data.email          ?? '';
    document.getElementById('sup_mst').value    = data.ma_so_thue     ?? '';
    document.getElementById('sup_dc').value     = data.dia_chi        ?? '';
    document.getElementById('sup_ghichu').value = data.ghi_chu        ?? '';
    // Khóa mã NCC khi sửa (tránh trùng lặp)
    document.getElementById('sup_ma').readOnly  = true;
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
