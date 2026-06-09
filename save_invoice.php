<?php
/**
 * save_invoice.php
 * Nhận dữ liệu hóa đơn qua POST (JSON),
 * dùng mPDF tạo file PDF và lưu vào kho_hoadon/.
 * Trả về JSON { success, filename, url }
 */
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

// Load Composer autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'error' => 'mPDF chưa được cài đặt (vendor/autoload.php không tìm thấy)']);
    exit;
}
require_once $autoload;

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['ma_hd'])) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ']);
    exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function fmt(float $n): string {
    return number_format($n, 0, ',', '.') . ' đ';
}

// -------------------------------------------------------
// Chuẩn bị dữ liệu
// -------------------------------------------------------
$ma_hd   = $data['ma_hd']      ?? '';
$ngay    = $data['ngay_gio']   ?? date('d/m/Y H:i:s');
$items   = $data['items']      ?? [];
$tong    = (float)($data['tong_tien']      ?? 0);
$phuong_thuc = ($data['phuong_thuc'] ?? 'tien_mat') === 'tien_mat' ? 'Tiền mặt' : 'Chuyển khoản';
$isCash  = ($data['phuong_thuc'] ?? 'tien_mat') === 'tien_mat';
$khach_dua = (float)($data['tien_khach_dua'] ?? 0);
$thoi      = (float)($data['tien_thoi']      ?? 0);

// -------------------------------------------------------
// Build HTML cho mPDF (khổ 80mm nhiệt – dùng A7 portrait)
// -------------------------------------------------------
$rows = '';
foreach ($items as $item) {
    $name  = htmlspecialchars($item['name'] ?? '', ENT_QUOTES);
    $qty   = (int)($item['qty']   ?? 0);
    $price = (float)($item['price'] ?? 0);
    $giam  = (float)($item['discount'] ?? 0);
    $tt    = (float)($item['thanh_tien'] ?? 0);
    $giam_txt = $giam > 0 ? " <small style='color:#c00'>(-{$giam}%)</small>" : '';

    $rows .= "
    <tr>
        <td class='td-left'>{$name}{$giam_txt}</td>
        <td class='td-center'>{$qty}</td>
        <td class='td-right'>" . fmt($price) . "</td>
        <td class='td-right'>" . fmt($tt) . "</td>
    </tr>";
}

$cash_rows = '';
if ($isCash) {
    $cash_rows = "
    <tr>
        <td colspan='3' class='td-right'>Khách đưa:</td>
        <td class='td-right'>" . fmt($khach_dua) . "</td>
    </tr>
    <tr>
        <td colspan='3' class='td-right'>Tiền thối:</td>
        <td class='td-right' style='color:#080'>" . fmt($thoi) . "</td>
    </tr>";
}

$html = <<<HTML
<html>
<head>
<meta charset="UTF-8">
<style>
    body        { font-family: dejavusansmono, monospace; font-size: 9pt; color: #111; }
    .center     { text-align: center; }
    .shop-name  { font-size: 13pt; font-weight: bold; text-align: center; }
    .shop-sub   { font-size: 8pt; color: #555; text-align: center; }
    hr          { border: none; border-top: 1px dashed #999; margin: 4px 0; }
    table.meta  { width: 100%; font-size: 9pt; }
    table.items { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.items th { border-bottom: 1px solid #ccc; padding: 2px 2px; font-size: 8pt; }
    .td-left    { text-align: left;   padding: 2px 2px; }
    .td-center  { text-align: center; padding: 2px 2px; }
    .td-right   { text-align: right;  padding: 2px 2px; }
    .total-row  { font-weight: bold; font-size: 11pt; }
    .footer     { text-align: center; font-size: 7.5pt; color: #888; margin-top: 6px; }
</style>
</head>
<body>
    <div class="shop-name">THANH HẬU POS</div>
    <div class="shop-sub">Cửa hàng tiện lợi Thanh Hậu</div>
    <hr>

    <table class="meta" cellspacing="0" cellpadding="1">
        <tr><td>Mã HĐ</td><td>:</td><td><strong>{$ma_hd}</strong></td></tr>
        <tr><td>Ngày</td><td>:</td><td>{$ngay}</td></tr>
        <tr><td>PT TT</td><td>:</td><td>{$phuong_thuc}</td></tr>
    </table>

    <hr>

    <table class="items">
        <thead>
            <tr>
                <th style="text-align:left">Sản phẩm</th>
                <th style="text-align:center">SL</th>
                <th style="text-align:right">Đơn giá</th>
                <th style="text-align:right">T.Tiền</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
        <tfoot>
            <tr><td colspan="4"><hr></td></tr>
            <tr class="total-row">
                <td colspan="3" class="td-right">TỔNG CỘNG:</td>
                <td class="td-right" style="color:#c00">{$tong_fmt}</td>
            </tr>
            {$cash_rows}
        </tfoot>
    </table>

    <hr>
    <div class="footer">Cảm ơn quý khách! Hẹn gặp lại.<br>Thanh Hậu POS</div>
</body>
</html>
HTML;

$html = str_replace('{$tong_fmt}', fmt($tong), $html);

// -------------------------------------------------------
// Tạo PDF bằng mPDF (khổ A7 = ~74×105mm, phù hợp hóa đơn 80mm)
// -------------------------------------------------------
$dir      = __DIR__ . '/kho_hoadon/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$safe_ma  = preg_replace('/[^A-Za-z0-9_\-]/', '', $ma_hd);
$filename = $safe_ma . '_' . date('Ymd_His') . '.pdf';
$filepath = $dir . $filename;

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => [74, 200],   // width 74mm, height tự co giãn
        'margin_top'    => 5,
        'margin_bottom' => 5,
        'margin_left'   => 4,
        'margin_right'  => 4,
        'tempDir'       => sys_get_temp_dir(),
    ]);
    $mpdf->SetTitle('Hóa đơn ' . $ma_hd);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'mPDF lỗi: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'success'  => true,
    'filename' => $filename,
    'url'      => 'kho_hoadon/' . rawurlencode($filename),
]);
