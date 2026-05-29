<?php 
include 'connect.php'; 

// Thêm, sửa, xóa Phiếu Nhập
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add' || $action == 'edit') {
            $ma_phieu = $_POST['ma_phieu_nhap'];
            $id_ncc = $_POST['id_nha_cung_cap'];
            $tong_tien = $_POST['tong_tien'];
            $ghi_chu = $_POST['ghi_chu'] ?? '';
            // Gán id_nguoi_lap mặc định là admin đang đăng nhập (nếu có_SESSION)
            $id_nguoi_lap = $_SESSION['user_id'] ?? 1; 
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO phieu_nhap (ma_phieu_nhap, id_nha_cung_cap, id_nguoi_lap, tong_tien, ghi_chu) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("siids", $ma_phieu, $id_ncc, $id_nguoi_lap, $tong_tien, $ghi_chu);
                $stmt->execute();
            } else {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE phieu_nhap SET ma_phieu_nhap=?, id_nha_cung_cap=?, tong_tien=?, ghi_chu=? WHERE id=?");
                $stmt->bind_param("sidsi", $ma_phieu, $id_ncc, $tong_tien, $ghi_chu, $id);
                $stmt->execute();
            }
        } elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM phieu_nhap WHERE id=$id");
        }
        header("Location: imports.php");
        exit();
    }
}

// Xử lý Nhà cung cấp (Nếu chưa có thì tự động tạo 1 NCC mẫu)
$check_ncc = $conn->query("SELECT * FROM nha_cung_cap");
if ($check_ncc->num_rows == 0) {
    $conn->query("INSERT INTO nha_cung_cap (ma_ncc, ten_ncc) VALUES ('NCC01', 'Nhà cung cấp mẫu')");
    $check_ncc = $conn->query("SELECT * FROM nha_cung_cap");
}
$nha_cung_cap = [];
while ($row = $check_ncc->fetch_assoc()) {
    $nha_cung_cap[] = $row;
}
$list_ncc = array_column($nha_cung_cap, 'ten_ncc', 'id');

// Lấy danh sách phiếu nhập
$sql_phieu = "
    SELECT pn.*, ncc.ten_ncc, tk.ho_ten as nguoi_lap 
    FROM phieu_nhap pn 
    LEFT JOIN nha_cung_cap ncc ON pn.id_nha_cung_cap = ncc.id 
    LEFT JOIN tai_khoan tk ON pn.id_nguoi_lap = tk.id
    ORDER BY pn.id DESC
";
$result_phieu = $conn->query($sql_phieu);
$imports = [];
$total_tien = 0;
while ($row = $result_phieu->fetch_assoc()) {
    $imports[] = $row;
    $total_tien += $row['tong_tien'];
}

include 'includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Lịch sử Nhập hàng</h3>
        <p class="text-muted mb-0">Quản lý phiếu nhập hàng từ nhà cung cấp</p>
    </div>
    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#importModal" onclick="resetForm()">
        <i class="fas fa-plus me-2"></i> Tạo phiếu nhập mới
    </button>
</div>

<!-- Thống kê ngắn -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 bg-primary bg-opacity-10 text-primary">
            <h5>Tổng phiếu nhập</h5>
            <h3><?= count($imports) ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 bg-success bg-opacity-10 text-success">
            <h5>Tổng giá trị nhập</h5>
            <h3><?= number_format($total_tien, 0, ',', '.') ?> đ</h3>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mã Phiếu</th>
                    <th>Nhà cung cấp</th>
                    <th>Người lập</th>
                    <th>Ngày nhập</th>
                    <th>Tổng tiền (VNĐ)</th>
                    <th>Ghi chú</th>
                    <th class="text-end">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($imports as $pn): ?>
                <tr>
                    <td class="fw-bold"><?= $pn['ma_phieu_nhap'] ?></td>
                    <td><?= $pn['ten_ncc'] ?></td>
                    <td><?= $pn['nguoi_lap'] ?? 'Admin' ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($pn['ngay_nhap'])) ?></td>
                    <td class="text-danger fw-bold"><?= number_format($pn['tong_tien'], 0, ',', '.') ?> đ</td>
                    <td><?= htmlspecialchars($pn['ghi_chu']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='editImport(<?= json_encode($pn) ?>)'><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc xoá phiếu này?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $pn['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($imports)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">Chưa có phiếu nhập nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm phiếu nhập mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="actionField" value="add">
                <input type="hidden" name="id" id="idField" value="">
                
                <div class="mb-3">
                    <label class="form-label">Mã phiếu nhập</label>
                    <input type="text" name="ma_phieu_nhap" id="ma_phieu_nhap" class="form-control" placeholder="VD: PN001" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nhà cung cấp</label>
                    <select name="id_nha_cung_cap" id="id_nha_cung_cap" class="form-select" required>
                        <?php foreach($nha_cung_cap as $ncc): ?>
                        <option value="<?= $ncc['id'] ?>"><?= $ncc['ten_ncc'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tổng tiền (VNĐ)</label>
                    <input type="number" name="tong_tien" id="tong_tien" class="form-control" required min="0" step="1000">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="ghi_chu" id="ghi_chu" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-custom">Lưu phiếu nhập</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Thêm phiếu nhập mới';
    document.getElementById('actionField').value = 'add';
    document.getElementById('idField').value = '';
    document.getElementById('ma_phieu_nhap').value = 'PN' + Math.floor(Math.random() * 10000);
    document.getElementById('tong_tien').value = 0;
    document.getElementById('ghi_chu').value = '';
}

function editImport(data) {
    document.getElementById('modalTitle').innerText = 'Sửa phiếu nhập';
    document.getElementById('actionField').value = 'edit';
    document.getElementById('idField').value = data.id;
    document.getElementById('ma_phieu_nhap').value = data.ma_phieu_nhap;
    document.getElementById('id_nha_cung_cap').value = data.id_nha_cung_cap;
    document.getElementById('tong_tien').value = data.tong_tien;
    document.getElementById('ghi_chu').value = data.ghi_chu;
    new bootstrap.Modal(document.getElementById('importModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>