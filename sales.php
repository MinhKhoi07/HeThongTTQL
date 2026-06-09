<?php 
include 'connect.php'; 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nhan_vien_id = $_SESSION['user_id'] ?? 1;

// Tự động tạo bảng chi_tiet_hoa_don nếu chưa có
$conn->query("CREATE TABLE IF NOT EXISTS `chi_tiet_hoa_don` (
    `id`           int(11)       NOT NULL AUTO_INCREMENT,
    `id_hoa_don`   int(11)       NOT NULL,
    `id_san_pham`  int(11)       NOT NULL,
    `so_luong`     int(11)       NOT NULL DEFAULT 1,
    `gia_ban`      decimal(15,2) NOT NULL DEFAULT 0.00,
    `muc_giam_gia` decimal(5,2)  DEFAULT 0.00,
    `thanh_tien`   decimal(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `id_hoa_don`  (`id_hoa_don`),
    KEY `id_san_pham` (`id_san_pham`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci");

// Xử lý Thanh Toán
$invoice_data = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $cart            = json_decode($_POST['cart_data'], true);
    $tong_tien       = (float)($_POST['tong_tien']       ?? 0);
    $tien_khach_dua  = (float)($_POST['tien_khach_dua']  ?? $tong_tien);
    $tien_thoi       = $tien_khach_dua - $tong_tien;
    $phuong_thuc_tt  = $_POST['phuong_thuc_tt'] ?? 'tien_mat';

    if ($cart && is_array($cart)) {
        $conn->begin_transaction();
        try {
            $ma_hd   = 'HD' . time();
            $stmt_hd = $conn->prepare("INSERT INTO hoa_don (ma_hoa_don, tong_tien) VALUES (?, ?)");
            $stmt_hd->bind_param("sd", $ma_hd, $tong_tien);
            $stmt_hd->execute();
            $id_hoa_don = $stmt_hd->insert_id;

            $stmt_ct = $conn->prepare("INSERT INTO chi_tiet_hoa_don (id_hoa_don, id_san_pham, so_luong, gia_ban, muc_giam_gia, thanh_tien) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_tk = $conn->prepare("UPDATE ton_kho SET so_luong = GREATEST(so_luong - ?, 0) WHERE id_san_pham = ?");

            $invoice_items = [];
            foreach ($cart as $item) {
                $id_sp      = (int)$item['id'];
                $qty        = (int)$item['qty'];
                $gia        = (float)$item['price'];
                $giam       = (float)($item['discount'] ?? 0);
                $thanh_tien = ($gia - ($gia * $giam / 100)) * $qty;

                $stmt_ct->bind_param("iiiddd", $id_hoa_don, $id_sp, $qty, $gia, $giam, $thanh_tien);
                $stmt_ct->execute();

                $stmt_tk->bind_param("ii", $qty, $id_sp);
                $stmt_tk->execute();

                $invoice_items[] = [
                    'name'       => $item['name'],
                    'qty'        => $qty,
                    'price'      => $gia,
                    'discount'   => $giam,
                    'thanh_tien' => $thanh_tien,
                ];
            }

            $conn->commit();

            // Dữ liệu hóa đơn để hiển thị ngay
            $invoice_data = [
                'ma_hd'          => $ma_hd,
                'ngay_gio'       => date('d/m/Y H:i:s'),
                'items'          => $invoice_items,
                'tong_tien'      => $tong_tien,
                'phuong_thuc'    => $phuong_thuc_tt,
                'tien_khach_dua' => $tien_khach_dua,
                'tien_thoi'      => $tien_thoi,
            ];
        } catch (Exception $e) {
            $conn->rollback();
            $msg_err = "Lỗi thanh toán: " . $e->getMessage();
        }
    }
}

// Lấy danh sách sản phẩm kèm tồn kho
$sql = "SELECT sp.id, sp.ten_san_pham, sp.gia_ban, sp.ma_vach, sp.ma_sku, sp.hinh_anh, sp.id_danh_muc,
        COALESCE(SUM(tk.so_luong), 0) as ton_kho
        FROM san_pham sp
        LEFT JOIN ton_kho tk ON sp.id = tk.id_san_pham
        GROUP BY sp.id
        ORDER BY sp.id DESC";
$result = $conn->query($sql);

// Lấy khuyến mãi đang diễn ra
$now    = date('Y-m-d H:i:s');
$sql_km = "SELECT * FROM khuyen_mai WHERE ngay_bat_dau <= '$now' AND ngay_ket_thuc >= '$now'";
$km_result  = $conn->query($sql_km);
$promotions = [];
while ($km = $km_result->fetch_assoc()) $promotions[] = $km;

$sp_km = [];
$ctkm  = $conn->query("SELECT id_san_pham, id_khuyen_mai FROM chi_tiet_khuyen_mai");
while ($ct = $ctkm->fetch_assoc()) $sp_km[$ct['id_san_pham']][] = $ct['id_khuyen_mai'];

$products = [];
while ($row = $result->fetch_assoc()) {
    $max_discount = 0;
    foreach ($promotions as $km) {
        $km_id = $km['id'];
        $val   = (float)$km['muc_giam'];
        $is_apply = false;
        if ($km['loai_ap_dung'] == 'tat_ca') $is_apply = true;
        elseif ($km['loai_ap_dung'] == 'danh_muc' && $km['gia_tri_ap_dung'] == $row['id_danh_muc']) $is_apply = true;
        elseif ($km['loai_ap_dung'] == 'san_pham' && isset($sp_km[$row['id']]) && in_array($km_id, $sp_km[$row['id']])) $is_apply = true;
        if ($is_apply && $val > $max_discount) $max_discount = $val;
    }
    $row['discount'] = $max_discount;
    $products[] = $row;
}

// Khách hàng
$conn->query("CREATE TABLE IF NOT EXISTS `khach_hang` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `ma_khach_hang` varchar(20) DEFAULT NULL,
  `ten_khach_hang` varchar(100) NOT NULL, `so_dien_thoai` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL, `dia_chi` varchar(255) DEFAULT NULL,
  `ngay_sinh` date DEFAULT NULL, `diem_tich_luy` int(11) DEFAULT 0,
  `ngay_tao` datetime DEFAULT current_timestamp(), `ghi_chu` text DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `ma_khach_hang` (`ma_khach_hang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci");
$check_kh = $conn->query("SELECT COUNT(*) as c FROM khach_hang")->fetch_assoc();
if ($check_kh['c'] == 0) $conn->query("INSERT INTO khach_hang (ma_khach_hang, ten_khach_hang, diem_tich_luy) VALUES ('KH001', 'Khách lẻ', 0)");
$customers = $conn->query("SELECT id, ten_khach_hang, so_dien_thoai FROM khach_hang")->fetch_all(MYSQLI_ASSOC);

// Cấu hình VietQR
define('VCB_BANK_ID',   '970436');   // Vietcombank BIN
define('VCB_ACCOUNT',   '1062858994');
define('VCB_ACC_NAME',  'NGUYEN MINH KHOI');

include 'includes/header.php'; 
?>

<!-- ============================================================
     CSS PRINT - Mẫu hóa đơn dùng khi in / xuất PDF
     ============================================================ -->
<style>
/* Ẩn toàn bộ trang khi in, chỉ hiện #printable-invoice */
@media print {
    body * { visibility: hidden !important; }
    #printable-invoice, #printable-invoice * { visibility: visible !important; }
    #printable-invoice {
        position: fixed !important;
        top: 0; left: 0;
        width: 80mm;
        padding: 6mm;
        font-family: 'Courier New', monospace;
        font-size: 11px;
        color: #000;
    }
    .no-print { display: none !important; }
}
/* Xem trước hóa đơn trong modal */
#printable-invoice {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    color: #111;
}
#printable-invoice .inv-title  { text-align: center; font-weight: bold; font-size: 15px; }
#printable-invoice .inv-shop   { text-align: center; font-size: 11px; color: #555; }
#printable-invoice .inv-hr     { border-top: 1px dashed #aaa; margin: 6px 0; }
#printable-invoice .inv-row    { display: flex; justify-content: space-between; margin: 2px 0; }
#printable-invoice .inv-total  { font-weight: bold; font-size: 14px; }
#printable-invoice .inv-footer { text-align: center; font-size: 10px; color: #888; margin-top: 8px; }
</style>

<div class="row">
    <!-- =========================================================
         Cột trái: Danh sách Sản phẩm
         ========================================================= -->
    <div class="col-lg-7 col-xl-8 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Hệ thống phân phối (POS)</h4>
            <?php if (isset($msg_err)): ?>
                <span class="text-danger fw-bold"><i class="fas fa-times-circle"></i> <?= $msg_err ?></span>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="posSearch" class="form-control border-start-0" placeholder="Tìm kiếm sản phẩm theo tên hoặc mã vạch..." onkeyup="filterProducts()">
            </div>
        </div>

        <div class="row g-3" id="posProductList">
            <?php foreach ($products as $p): ?>
            <div class="col-md-6 col-lg-4 product-item"
                 data-name="<?= strtolower($p['ten_san_pham']) ?>"
                 data-sku="<?= strtolower($p['ma_sku']) ?>"
                 data-barcode="<?= strtolower($p['ma_vach']) ?>">
                <?php $oos = $p['ton_kho'] <= 0; ?>
                <div class="card h-100 product-card shadow-sm border-0 <?= $oos ? 'opacity-50' : '' ?>"
                     data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>'
                     <?= !$oos ? 'onclick="addToCartFromElement(this)"' : '' ?>
                     style="cursor:<?= $oos ? 'not-allowed' : 'pointer' ?>;transition:transform .2s">
                    <?php if ($p['discount'] > 0): ?>
                        <div class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 m-2 rounded fw-bold" style="font-size:.8rem;z-index:2">-<?= $p['discount'] ?>%</div>
                    <?php endif; ?>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <?php if (!empty($p['hinh_anh'])): ?>
                            <img src="<?= htmlspecialchars($p['hinh_anh']) ?>" class="img-fluid mb-3 mx-auto" style="max-height:100px;object-fit:contain">
                        <?php else: ?>
                            <i class="fas fa-box fa-3x text-custom mb-3 opacity-50"></i>
                        <?php endif; ?>
                        <h6 class="fw-bold text-truncate" title="<?= $p['ten_san_pham'] ?>"><?= $p['ten_san_pham'] ?></h6>
                        <small class="text-muted d-block"><?= $p['ma_sku'] ?> | Tồn: <span class="<?= $oos ? 'text-danger fw-bold' : '' ?>"><?= $p['ton_kho'] ?></span></small>
                        <div class="mt-2">
                            <?php if ($p['discount'] > 0): ?>
                                <small class="text-decoration-line-through text-muted"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ</small>
                                <h5 class="text-danger fw-bold mb-0"><?= number_format($p['gia_ban'] - $p['gia_ban'] * $p['discount'] / 100, 0, ',', '.') ?> đ</h5>
                            <?php else: ?>
                                <h5 class="text-danger fw-bold mb-0"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ</h5>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <div class="col-12"><p class="text-muted text-center py-5">Chưa có sản phẩm nào.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- =========================================================
         Cột phải: Giỏ hàng
         ========================================================= -->
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm border-0 h-100 d-flex flex-column">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-shopping-cart text-custom me-2"></i> Giỏ hàng</h5>
            </div>

            <div class="card-body p-0 flex-grow-1" style="overflow-y:auto;max-height:45vh" id="cartContainer">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-cart-arrow-down fa-3x mb-3 opacity-25"></i>
                    <p>Giỏ hàng trống</p>
                </div>
            </div>

            <div class="card-footer bg-light border-top p-3">
                <form method="POST" id="checkoutForm" onsubmit="return handleCheckout()">

                    <!-- Khách hàng -->
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Khách hàng</label>
                        <select name="id_khach_hang" class="form-select form-select-sm">
                            <option value="">Khách Vãng Lai</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ten_khach_hang']) ?> (<?= htmlspecialchars($c['so_dien_thoai'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tổng tiền -->
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Tổng phụ:</span>
                        <span class="fw-bold" id="cartSubtotal">0 đ</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                        <span class="text-muted">Giảm giá:</span>
                        <span class="fw-bold text-success" id="cartDiscount">- 0 đ</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 align-items-center">
                        <h5 class="fw-bold mb-0">THÀNH TIỀN:</h5>
                        <h4 class="fw-bold text-danger mb-0" id="cartTotal">0 đ</h4>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Phương thức thanh toán</label>
                        <div class="d-flex gap-2">
                            <button type="button" id="btn_tien_mat" class="btn btn-sm btn-success flex-fill active-payment"
                                    onclick="setPayment('tien_mat')">
                                <i class="fas fa-money-bill-wave me-1"></i> Tiền mặt
                            </button>
                            <button type="button" id="btn_chuyen_khoan" class="btn btn-sm btn-outline-primary flex-fill"
                                    onclick="setPayment('chuyen_khoan')">
                                <i class="fas fa-qrcode me-1"></i> Chuyển khoản
                            </button>
                        </div>
                        <input type="hidden" name="phuong_thuc_tt" id="phuong_thuc_tt" value="tien_mat">
                    </div>

                    <!-- Tiền mặt: nhập tiền khách đưa -->
                    <div id="box_tien_mat" class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Khách đưa (đ)</label>
                            <input type="number" name="tien_khach_dua" id="tienKhachDua"
                                   class="form-control form-control-sm text-end fw-bold"
                                   placeholder="0" min="0" onkeyup="caculateChange()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Tiền thối</label>
                            <input type="text" id="tienThoiClient"
                                   class="form-control form-control-sm text-end fw-bold text-primary bg-white"
                                   placeholder="0 đ" readonly>
                        </div>
                    </div>

                    <!-- Chuyển khoản: QR VietQR -->
                    <div id="box_chuyen_khoan" style="display:none" class="mb-3 text-center">
                        <p class="small text-muted mb-1">Quét QR để chuyển đúng số tiền</p>
                        <img id="qr_image" src="" alt="QR thanh toán"
                             class="img-fluid rounded border shadow-sm"
                             style="max-width:200px">
                        <div class="mt-2 small text-muted">
                            <strong>Vietcombank</strong> – <?= VCB_ACCOUNT ?><br>
                            <?= VCB_ACC_NAME ?>
                        </div>
                        <!-- Tiền mặt ẩn vẫn cần gửi lên server -->
                        <input type="number" name="tien_khach_dua" id="tienKhachDuaHidden" value="0" style="display:none">
                    </div>

                    <input type="hidden" name="checkout"  value="1">
                    <input type="hidden" name="cart_data" id="cartDataInput">
                    <input type="hidden" name="tong_tien" id="tongTienInput" value="0">

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-custom btn-lg w-100 fs-5 fw-bold" id="btnCheckout" disabled>
                            <i class="fas fa-money-bill-wave me-2"></i> THANH TOÁN
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="fas fa-trash me-2"></i> Làm mới giỏ hàng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Modal hóa đơn sau thanh toán
     ============================================================ -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 no-print">
                <h6 class="modal-title fw-bold"><i class="fas fa-receipt me-2 text-success"></i>Thanh toán thành công</h6>
            </div>
            <div class="modal-body p-3">
                <!-- NỘI DUNG HÓA ĐƠN – id này dùng cho cả in lẫn hiển thị -->
                <div id="printable-invoice">
                    <div class="inv-title">THANH HẬU POS</div>
                    <div class="inv-shop">Cửa hàng tiện lợi Thanh Hậu</div>
                    <div class="inv-hr"></div>
                    <div class="inv-row"><span>Mã HĐ:</span> <span id="inv_ma_hd"></span></div>
                    <div class="inv-row"><span>Ngày:</span>   <span id="inv_ngay"></span></div>
                    <div class="inv-row"><span>PT TT:</span>  <span id="inv_pttt"></span></div>
                    <div class="inv-hr"></div>
                    <table style="width:100%;border-collapse:collapse;font-size:11px" id="inv_items_table">
                        <thead>
                            <tr>
                                <th style="text-align:left">Sản phẩm</th>
                                <th style="text-align:center">SL</th>
                                <th style="text-align:right">T.Tiền</th>
                            </tr>
                        </thead>
                        <tbody id="inv_items"></tbody>
                    </table>
                    <div class="inv-hr"></div>
                    <div class="inv-row inv-total"><span>TỔNG:</span>      <span id="inv_tong"></span></div>
                    <div class="inv-row" id="inv_row_khach"><span>Khách đưa:</span> <span id="inv_khach_dua"></span></div>
                    <div class="inv-row" id="inv_row_thoi"><span>Tiền thối:</span>  <span id="inv_thoi"></span></div>
                    <div class="inv-hr"></div>
                    <div class="inv-footer">Cảm ơn quý khách! Hẹn gặp lại.<br>Thanh Hậu POS</div>
                </div>
            </div>
            <div class="modal-footer py-2 no-print gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printInvoice()">
                    <i class="fas fa-print me-1"></i> In hóa đơn
                </button>
                <button type="button" class="btn btn-success btn-sm" data-bs-dismiss="modal" onclick="resetAfterCheckout()">
                    <i class="fas fa-check me-1"></i> Xong
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.active-payment { opacity: 1 !important; }
.product-card:hover { transform: translateY(-4px); border-color: #6C5CE7 !important; }
.cart-item { transition: all .3s; }
</style>

<script>
// ----------------------------------------------------------------
// Cấu hình VietQR
// ----------------------------------------------------------------
const VCB_BANK_ID  = '<?= VCB_BANK_ID ?>';
const VCB_ACCOUNT  = '<?= VCB_ACCOUNT ?>';
const VCB_ACC_NAME = '<?= VCB_ACC_NAME ?>';

function buildQrUrl(amount) {
    const desc = encodeURIComponent('Thanh Hau POS');
    return `https://img.vietqr.io/image/${VCB_BANK_ID}-${VCB_ACCOUNT}-compact2.png?amount=${Math.round(amount)}&addInfo=${desc}&accountName=${encodeURIComponent(VCB_ACC_NAME)}`;
}

// ----------------------------------------------------------------
// Phương thức thanh toán
// ----------------------------------------------------------------
let currentPayment = 'tien_mat';

function setPayment(method) {
    currentPayment = method;
    document.getElementById('phuong_thuc_tt').value = method;

    const btnTM = document.getElementById('btn_tien_mat');
    const btnCK = document.getElementById('btn_chuyen_khoan');
    const boxTM = document.getElementById('box_tien_mat');
    const boxCK = document.getElementById('box_chuyen_khoan');

    if (method === 'tien_mat') {
        btnTM.className = 'btn btn-sm btn-success flex-fill';
        btnCK.className = 'btn btn-sm btn-outline-primary flex-fill';
        boxTM.style.display = '';
        boxCK.style.display = 'none';
    } else {
        btnTM.className = 'btn btn-sm btn-outline-success flex-fill';
        btnCK.className = 'btn btn-sm btn-primary flex-fill';
        boxTM.style.display = 'none';
        boxCK.style.display = '';
        // Cập nhật QR theo tổng tiền hiện tại
        const total = parseFloat(document.getElementById('tongTienInput').value) || 0;
        document.getElementById('qr_image').src = buildQrUrl(total);
        document.getElementById('tienKhachDuaHidden').value = total;
    }
    caculateChange();
}

// ----------------------------------------------------------------
// Giỏ hàng
// ----------------------------------------------------------------
let cart = [];

function formatMoney(num) {
    return Number(num).toLocaleString('vi-VN') + ' đ';
}

function addToCartFromElement(el) {
    addToCart(JSON.parse(el.getAttribute('data-product')));
}

function addToCart(product) {
    if (product.ton_kho <= 0) return;
    const ex = cart.find(i => i.id == product.id);
    if (ex) {
        if (ex.qty < product.ton_kho) ex.qty++;
        else alert('Số lượng trong kho không đủ!');
    } else {
        cart.push({
            id: product.id, name: product.ten_san_pham,
            price: parseFloat(product.gia_ban),
            discount: parseFloat(product.discount || 0),
            max_qty: parseInt(product.ton_kho), qty: 1
        });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id == id);
    if (!item) return;
    if (delta > 0 && item.qty >= item.max_qty) { alert('Số lượng trong kho không đủ!'); return; }
    item.qty += delta;
    if (item.qty <= 0) cart = cart.filter(i => i.id != id);
    renderCart();
}

function renderCart() {
    const container   = document.getElementById('cartContainer');
    const subtotalEl  = document.getElementById('cartSubtotal');
    const discountEl  = document.getElementById('cartDiscount');
    const totalEl     = document.getElementById('cartTotal');
    const btnCheckout = document.getElementById('btnCheckout');

    if (cart.length === 0) {
        container.innerHTML = `<div class="text-center text-muted py-5">
            <i class="fas fa-cart-arrow-down fa-3x mb-3 opacity-25"></i><p>Giỏ hàng trống</p></div>`;
        subtotalEl.innerText = '0 đ'; discountEl.innerText = '- 0 đ'; totalEl.innerText = '0 đ';
        document.getElementById('tongTienInput').value = 0;
        btnCheckout.disabled = true;
        caculateChange(); return;
    }

    let html = '<ul class="list-group list-group-flush">';
    let subtotal = 0, totalDiscount = 0;
    cart.forEach(item => {
        const it = item.price * item.qty;
        const id = it * item.discount / 100;
        subtotal += it; totalDiscount += id;
        html += `<li class="list-group-item py-2 cart-item">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div class="fw-bold pe-2" style="font-size:.9rem">${item.name}</div>
                <div class="fw-bold text-danger">${formatMoney(it - id)}</div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">${formatMoney(item.price)}${item.discount > 0 ? ` <sup class="text-danger">(-${item.discount}%)</sup>` : ''}</small>
                <div class="input-group input-group-sm w-auto">
                    <button class="btn btn-outline-secondary" type="button" onclick="updateQty('${item.id}',-1)"><i class="fas fa-minus"></i></button>
                    <input type="text" class="form-control text-center fw-bold px-0" value="${item.qty}" style="width:36px" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="updateQty('${item.id}',1)"><i class="fas fa-plus"></i></button>
                </div>
            </div></li>`;
    });
    html += '</ul>';
    container.innerHTML = html;

    const endTotal = subtotal - totalDiscount;
    subtotalEl.innerText = formatMoney(subtotal);
    discountEl.innerText = '- ' + formatMoney(totalDiscount);
    totalEl.innerText    = formatMoney(endTotal);
    document.getElementById('tongTienInput').value = endTotal;
    document.getElementById('tienKhachDua').value  = endTotal;

    // Cập nhật QR nếu đang chọn chuyển khoản
    if (currentPayment === 'chuyen_khoan') {
        document.getElementById('qr_image').src = buildQrUrl(endTotal);
        document.getElementById('tienKhachDuaHidden').value = endTotal;
    }

    btnCheckout.disabled = false;
    caculateChange();
}

function caculateChange() {
    const tong     = parseFloat(document.getElementById('tongTienInput').value) || 0;
    const btn      = document.getElementById('btnCheckout');

    if (currentPayment === 'chuyen_khoan') {
        // Chuyển khoản: luôn hợp lệ (QR đã điền đúng số tiền)
        btn.disabled = cart.length === 0;
        return;
    }

    const khachDua = parseFloat(document.getElementById('tienKhachDua').value) || 0;
    const thoiEl   = document.getElementById('tienThoiClient');
    if (khachDua < tong) {
        thoiEl.value = 'Thiếu tiền';
        thoiEl.classList.remove('text-primary'); thoiEl.classList.add('text-danger');
        btn.disabled = true;
    } else {
        thoiEl.value = formatMoney(khachDua - tong);
        thoiEl.classList.remove('text-danger'); thoiEl.classList.add('text-primary');
        btn.disabled = cart.length === 0;
    }
}

function clearCart() {
    if (confirm('Chắc chắn làm mới giỏ hàng?')) { cart = []; renderCart(); }
}

function handleCheckout() {
    // Đồng bộ trường ẩn khi chuyển khoản
    if (currentPayment === 'chuyen_khoan') {
        document.getElementById('tienKhachDuaHidden').value = document.getElementById('tongTienInput').value;
    }
    document.getElementById('cartDataInput').value = JSON.stringify(cart);
    return true;
}

function filterProducts() {
    const term = document.getElementById('posSearch').value.toLowerCase();
    document.querySelectorAll('.product-item').forEach(el => {
        const ok = el.dataset.name.includes(term) || el.dataset.sku.includes(term) || el.dataset.barcode.includes(term);
        el.style.display = ok ? '' : 'none';
    });
}

// ----------------------------------------------------------------
// Hóa đơn
// ----------------------------------------------------------------
let currentInvoiceData = null;

function renderInvoice(data) {
    currentInvoiceData = data; // Lưu để dùng cho printInvoice()
    document.getElementById('inv_ma_hd').textContent  = data.ma_hd;
    document.getElementById('inv_ngay').textContent   = data.ngay_gio;
    document.getElementById('inv_pttt').textContent   = data.phuong_thuc === 'tien_mat' ? 'Tiền mặt' : 'Chuyển khoản';
    document.getElementById('inv_tong').textContent   = formatMoney(data.tong_tien);

    const isCash = data.phuong_thuc === 'tien_mat';
    document.getElementById('inv_row_khach').style.display = isCash ? '' : 'none';
    document.getElementById('inv_row_thoi').style.display  = isCash ? '' : 'none';
    if (isCash) {
        document.getElementById('inv_khach_dua').textContent = formatMoney(data.tien_khach_dua);
        document.getElementById('inv_thoi').textContent      = formatMoney(data.tien_thoi);
    }

    const tbody = document.getElementById('inv_items');
    tbody.innerHTML = '';
    data.items.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align:left;padding:1px 0">${item.name}${item.discount > 0 ? ` <small>(-${item.discount}%)</small>` : ''}</td>
            <td style="text-align:center;padding:1px 2px">${item.qty}</td>
            <td style="text-align:right;padding:1px 0">${formatMoney(item.thanh_tien)}</td>`;
        tbody.appendChild(tr);
    });

    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();
}

// Lưu hóa đơn vào kho_hoadon/ rồi mở tab mới để in
function printInvoice() {
    if (!currentInvoiceData) { window.print(); return; }

    const btn = document.querySelector('#invoiceModal .modal-footer .btn-outline-secondary');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang lưu...';

    fetch('save_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(currentInvoiceData)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled  = false;
        btn.innerHTML = origText;
        if (res.success) {
            // Mở file HTML hóa đơn trong tab mới → người dùng Ctrl+P hoặc bấm nút In
            window.open(res.url, '_blank');
        } else {
            alert('Lỗi lưu hóa đơn: ' + (res.error || 'Không rõ'));
        }
    })
    .catch(() => {
        btn.disabled  = false;
        btn.innerHTML = origText;
        // Fallback: in trực tiếp trang hiện tại
        window.print();
    });
}

function resetAfterCheckout() {
    cart = [];
    renderCart();
}

// ----------------------------------------------------------------
// Hiển thị hóa đơn ngay sau khi trang load (nếu vừa thanh toán)
// ----------------------------------------------------------------
<?php if ($invoice_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    renderInvoice(<?= json_encode($invoice_data) ?>);
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
