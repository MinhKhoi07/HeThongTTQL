<?php 
include 'connect.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

// Tự động tạo bảng chi_tiet_phieu_nhap nếu chưa có
$conn->query("
    CREATE TABLE IF NOT EXISTS `chi_tiet_phieu_nhap` (
        `id`            int(11)        NOT NULL AUTO_INCREMENT,
        `id_phieu_nhap` int(11)        NOT NULL,
        `id_san_pham`   int(11)        NOT NULL,
        `so_luong`      int(11)        NOT NULL DEFAULT 1,
        `gia_nhap`      decimal(15,2)  NOT NULL DEFAULT 0.00,
        `thanh_tien`    decimal(15,2)  NOT NULL DEFAULT 0.00,
        PRIMARY KEY (`id`),
        KEY `id_phieu_nhap` (`id_phieu_nhap`),
        KEY `id_san_pham`   (`id_san_pham`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci
");

// -------------------------------------------------------
// Xử lý POST
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add' || $action == 'edit') {
        $ma_phieu    = trim($_POST['ma_phieu_nhap']);
        $id_ncc      = (int)$_POST['id_nha_cung_cap'];
        $ghi_chu     = $_POST['ghi_chu'] ?? '';
        $id_nguoi_lap = $_SESSION['user_id'] ?? 1;

        // Chi tiết sản phẩm từ form (mảng)
        $sp_ids   = $_POST['ct_sp_id']  ?? [];
        $sp_sls   = $_POST['ct_sl']     ?? [];
        $sp_gias  = $_POST['ct_gia']    ?? [];

        // Tính tổng tiền từ chi tiết
        $tong_tien = 0;
        $items = [];
        foreach ($sp_ids as $idx => $sp_id) {
            $sp_id = (int)$sp_id;
            $sl    = max(1, (int)($sp_sls[$idx] ?? 1));
            $gia   = max(0, (float)($sp_gias[$idx] ?? 0));
            if ($sp_id <= 0) continue;
            $tt = $sl * $gia;
            $tong_tien += $tt;
            $items[] = ['id' => $sp_id, 'sl' => $sl, 'gia' => $gia, 'tt' => $tt];
        }

        $conn->begin_transaction();
        try {
            if ($action == 'add') {
                // Thêm phiếu nhập
                $stmt = $conn->prepare("INSERT INTO phieu_nhap (ma_phieu_nhap, id_nha_cung_cap, id_nguoi_lap, tong_tien, ghi_chu) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("siids", $ma_phieu, $id_ncc, $id_nguoi_lap, $tong_tien, $ghi_chu);
                $stmt->execute();
                $id_phieu = $stmt->insert_id;
            } else {
                $id_phieu = (int)$_POST['id'];
                // Lấy chi tiết cũ để hoàn tồn kho trước khi ghi đè
                $old_items = $conn->query("SELECT id_san_pham, so_luong FROM chi_tiet_phieu_nhap WHERE id_phieu_nhap = $id_phieu");
                while ($oi = $old_items->fetch_assoc()) {
                    $conn->query("UPDATE ton_kho SET so_luong = GREATEST(so_luong - {$oi['so_luong']}, 0) WHERE id_san_pham = {$oi['id_san_pham']}");
                }
                // Xoá chi tiết cũ
                $conn->query("DELETE FROM chi_tiet_phieu_nhap WHERE id_phieu_nhap = $id_phieu");
                // Cập nhật header phiếu
                $stmt = $conn->prepare("UPDATE phieu_nhap SET ma_phieu_nhap=?, id_nha_cung_cap=?, tong_tien=?, ghi_chu=? WHERE id=?");
                $stmt->bind_param("sidsi", $ma_phieu, $id_ncc, $tong_tien, $ghi_chu, $id_phieu);
                $stmt->execute();
            }

            // Đảm bảo kho mặc định tồn tại
            $conn->query("INSERT IGNORE INTO kho_hang (id, ma_vi_tri, ten_vi_tri) VALUES (1, 'KHO_MAC_DINH', 'Kho Chính')");

            // Merge các dòng trùng sản phẩm trước khi lưu
            $merged = [];
            foreach ($items as $it) {
                if (isset($merged[$it['id']])) {
                    $merged[$it['id']]['sl'] += $it['sl'];
                    $merged[$it['id']]['tt'] += $it['tt'];
                } else {
                    $merged[$it['id']] = $it;
                }
            }
            $items = array_values($merged);

            // Thêm chi tiết & cập nhật tồn kho
            $stmt_ct = $conn->prepare("INSERT INTO chi_tiet_phieu_nhap (id_phieu_nhap, id_san_pham, so_luong, gia_nhap, thanh_tien) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $it) {
                // i=id_phieu, i=id_sp, i=so_luong, d=gia_nhap, d=thanh_tien
                $stmt_ct->bind_param("iiidd", $id_phieu, $it['id'], $it['sl'], $it['gia'], $it['tt']);
                $stmt_ct->execute();

                // Cộng vào tồn kho (upsert)
                $check_tk = $conn->query("SELECT id FROM ton_kho WHERE id_san_pham = {$it['id']}");
                if ($check_tk->num_rows > 0) {
                    $conn->query("UPDATE ton_kho SET so_luong = so_luong + {$it['sl']} WHERE id_san_pham = {$it['id']}");
                } else {
                    $conn->query("INSERT INTO ton_kho (id_san_pham, id_kho_hang, so_luong) VALUES ({$it['id']}, 1, {$it['sl']})");
                }
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Lỗi: " . $e->getMessage();
        }

    } elseif ($action == 'delete') {
        $id_phieu = (int)$_POST['id'];
        $conn->begin_transaction();
        try {
            // Hoàn tồn kho
            $old_items = $conn->query("SELECT id_san_pham, so_luong FROM chi_tiet_phieu_nhap WHERE id_phieu_nhap = $id_phieu");
            while ($oi = $old_items->fetch_assoc()) {
                $conn->query("UPDATE ton_kho SET so_luong = GREATEST(so_luong - {$oi['so_luong']}, 0) WHERE id_san_pham = {$oi['id_san_pham']}");
            }
            $conn->query("DELETE FROM chi_tiet_phieu_nhap WHERE id_phieu_nhap = $id_phieu");
            $conn->query("DELETE FROM phieu_nhap WHERE id = $id_phieu");
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
        }
    }

    header("Location: imports.php");
    exit();
}

// -------------------------------------------------------
// Lấy dữ liệu hiển thị
// -------------------------------------------------------

// Nhà cung cấp (tạo mẫu nếu chưa có)
$check_ncc = $conn->query("SELECT * FROM nha_cung_cap");
if ($check_ncc->num_rows == 0) {
    $conn->query("INSERT INTO nha_cung_cap (ma_ncc, ten_ncc) VALUES ('NCC01', 'Nhà cung cấp mẫu')");
    $check_ncc = $conn->query("SELECT * FROM nha_cung_cap");
}
$nha_cung_cap = [];
while ($row = $check_ncc->fetch_assoc()) $nha_cung_cap[] = $row;

// Danh sách sản phẩm (cho dropdown trong modal)
$all_products = $conn->query("SELECT id, ma_sku, ten_san_pham, gia_nhap FROM san_pham ORDER BY ten_san_pham")->fetch_all(MYSQLI_ASSOC);

// Danh sách phiếu nhập
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
    // Lấy chi tiết từng phiếu
    $ct = $conn->query("
        SELECT ctp.*, sp.ten_san_pham, sp.ma_sku 
        FROM chi_tiet_phieu_nhap ctp 
        JOIN san_pham sp ON ctp.id_san_pham = sp.id 
        WHERE ctp.id_phieu_nhap = {$row['id']}
    ");
    $row['chi_tiet'] = $ct ? $ct->fetch_all(MYSQLI_ASSOC) : [];
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

<?php if (isset($error_msg)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

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
                    <th>Sản phẩm</th>
                    <th>Tổng tiền (VNĐ)</th>
                    <th>Ghi chú</th>
                    <th class="text-end">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($imports as $pn): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($pn['ma_phieu_nhap']) ?></td>
                    <td><?= htmlspecialchars($pn['ten_ncc']) ?></td>
                    <td><?= htmlspecialchars($pn['nguoi_lap'] ?? 'Admin') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($pn['ngay_nhap'])) ?></td>
                    <td>
                        <?php if (!empty($pn['chi_tiet'])): ?>
                            <ul class="mb-0 ps-3" style="font-size:0.85rem;">
                                <?php foreach ($pn['chi_tiet'] as $ct): ?>
                                <li><?= htmlspecialchars($ct['ten_san_pham']) ?> &times; <?= $ct['so_luong'] ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-danger fw-bold"><?= number_format($pn['tong_tien'], 0, ',', '.') ?> đ</td>
                    <td><?= htmlspecialchars($pn['ghi_chu']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick='editImport(<?= htmlspecialchars(json_encode($pn, JSON_HEX_QUOT | JSON_HEX_APOS), ENT_QUOTES) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc xoá phiếu này? Tồn kho sẽ được hoàn lại.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $pn['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($imports)): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">Chưa có phiếu nhập nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm / Sửa Phiếu Nhập -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" id="importForm" onsubmit="return validateForm()">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm phiếu nhập mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="actionField" value="add">
                <input type="hidden" name="id"     id="idField"     value="">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Mã phiếu nhập</label>
                        <input type="text" name="ma_phieu_nhap" id="ma_phieu_nhap" class="form-control" placeholder="VD: PN001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nhà cung cấp</label>
                        <select name="id_nha_cung_cap" id="id_nha_cung_cap" class="form-select" required>
                            <?php foreach($nha_cung_cap as $ncc): ?>
                            <option value="<?= $ncc['id'] ?>"><?= htmlspecialchars($ncc['ten_ncc']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Danh sách sản phẩm nhập -->
                <label class="form-label fw-bold">Chi tiết sản phẩm nhập</label>
                <div class="table-responsive mb-2">
                    <table class="table table-bordered table-sm align-middle" id="ct_table">
                        <thead class="table-light">
                            <tr>
                                <th>Sản phẩm</th>
                                <th style="width:100px">Số lượng</th>
                                <th style="width:140px">Giá nhập (đ)</th>
                                <th style="width:140px">Thành tiền</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="ct_tbody">
                            <!-- các dòng sẽ được thêm bằng JS -->
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addRow()">
                    <i class="fas fa-plus me-1"></i> Thêm sản phẩm
                </button>

                <div class="d-flex justify-content-end align-items-center gap-3 mb-2">
                    <span class="fw-bold text-muted">Tổng tiền phiếu:</span>
                    <span class="fw-bold text-danger fs-5" id="modal_tong_tien">0 đ</span>
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
// Danh sách sản phẩm từ PHP để dùng trong JS
const allProducts = <?= json_encode($all_products) ?>;

function formatMoney(n) {
    return parseFloat(n || 0).toLocaleString('vi-VN') + ' đ';
}

function buildProductOptions(selectedId) {
    return allProducts.map(p =>
        `<option value="${p.id}" data-gia="${p.gia_nhap}" ${p.id == selectedId ? 'selected' : ''}>
            ${p.ma_sku} - ${p.ten_san_pham}
        </option>`
    ).join('');
}

function addRow(spId = '', sl = 1, gia = 0) {
    const tbody = document.getElementById('ct_tbody');
    const tr = document.createElement('tr');
    const tt = sl * gia;
    tr.innerHTML = `
        <td>
            <select name="ct_sp_id[]" class="form-select form-select-sm sp-select" onchange="onSelectProduct(this)" required>
                <option value="">-- Chọn sản phẩm --</option>
                ${buildProductOptions(spId)}
            </select>
        </td>
        <td>
            <input type="number" name="ct_sl[]" class="form-control form-control-sm sl-input" 
                   value="${sl}" min="1" onchange="recalcRow(this)" required>
        </td>
        <td>
            <input type="number" name="ct_gia[]" class="form-control form-control-sm gia-input" 
                   value="${gia}" min="0" step="100" onchange="recalcRow(this)" required>
        </td>
        <td class="tt-cell fw-bold text-danger">${formatMoney(tt)}</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    updateTotal();
}

function onSelectProduct(sel) {
    const opt = sel.options[sel.selectedIndex];
    const gia = parseFloat(opt.getAttribute('data-gia')) || 0;
    const row = sel.closest('tr');
    row.querySelector('.gia-input').value = gia;
    recalcRow(row.querySelector('.gia-input'));
}

function recalcRow(input) {
    const row = input.closest('tr');
    const sl  = parseFloat(row.querySelector('.sl-input').value)  || 0;
    const gia = parseFloat(row.querySelector('.gia-input').value) || 0;
    row.querySelector('.tt-cell').textContent = formatMoney(sl * gia);
    updateTotal();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateTotal();
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('#ct_tbody tr').forEach(tr => {
        const sl  = parseFloat(tr.querySelector('.sl-input')?.value)  || 0;
        const gia = parseFloat(tr.querySelector('.gia-input')?.value) || 0;
        total += sl * gia;
    });
    document.getElementById('modal_tong_tien').textContent = formatMoney(total);
}

function validateForm() {
    // Kiểm tra có ít nhất 1 dòng sản phẩm hợp lệ
    const rows = document.querySelectorAll('#ct_tbody tr');
    if (rows.length === 0) {
        alert('Vui lòng thêm ít nhất 1 sản phẩm vào phiếu nhập!');
        return false;
    }
    let valid = true;
    rows.forEach((tr, idx) => {
        const spSel = tr.querySelector('.sp-select');
        const slInput = tr.querySelector('.sl-input');
        const giaInput = tr.querySelector('.gia-input');

        if (!spSel.value) {
            spSel.classList.add('is-invalid');
            valid = false;
        } else {
            spSel.classList.remove('is-invalid');
        }
        if (!slInput.value || parseInt(slInput.value) < 1) {
            slInput.classList.add('is-invalid');
            valid = false;
        } else {
            slInput.classList.remove('is-invalid');
        }
        if (!giaInput.value || parseFloat(giaInput.value) < 0) {
            giaInput.classList.add('is-invalid');
            valid = false;
        } else {
            giaInput.classList.remove('is-invalid');
        }
    });
    if (!valid) {
        alert('Vui lòng điền đầy đủ thông tin sản phẩm (chọn sản phẩm, số lượng ≥ 1)!');
        return false;
    }
    // Kiểm tra trùng sản phẩm
    const spIds = Array.from(document.querySelectorAll('#ct_tbody .sp-select')).map(s => s.value).filter(v => v);
    const unique = new Set(spIds);
    if (unique.size < spIds.length) {
        if (!confirm('Có sản phẩm bị chọn trùng. Hệ thống sẽ tự gộp số lượng lại. Bạn có muốn tiếp tục?')) {
            return false;
        }
    }
    return true;
}

function resetForm() {
    document.getElementById('modalTitle').innerText = 'Thêm phiếu nhập mới';
    document.getElementById('actionField').value = 'add';
    document.getElementById('idField').value = '';
    document.getElementById('ma_phieu_nhap').value = 'PN' + Date.now().toString().slice(-5);
    document.getElementById('ghi_chu').value = '';
    document.getElementById('ct_tbody').innerHTML = '';
    document.getElementById('modal_tong_tien').textContent = '0 đ';
    addRow(); // Bắt đầu với 1 dòng trống
}

function editImport(data) {
    document.getElementById('modalTitle').innerText = 'Sửa phiếu nhập';
    document.getElementById('actionField').value = 'edit';
    document.getElementById('idField').value = data.id;
    document.getElementById('ma_phieu_nhap').value = data.ma_phieu_nhap;
    document.getElementById('id_nha_cung_cap').value = data.id_nha_cung_cap;
    document.getElementById('ghi_chu').value = data.ghi_chu || '';
    document.getElementById('ct_tbody').innerHTML = '';

    if (data.chi_tiet && data.chi_tiet.length > 0) {
        data.chi_tiet.forEach(ct => {
            addRow(ct.id_san_pham, ct.so_luong, ct.gia_nhap);
        });
    } else {
        addRow();
    }
    new bootstrap.Modal(document.getElementById('importModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
