-- Tạo bảng hóa đơn để lưu các giao dịch bán hàng
CREATE TABLE IF NOT EXISTS `hoa_don` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ma_hoa_don` varchar(20) NOT NULL,
  `ngay_tao` datetime DEFAULT current_timestamp(),
  `tong_tien` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tong_giam_gia` decimal(15,2) DEFAULT 0.00,
  `thanh_tien` decimal(15,2) NOT NULL DEFAULT 0.00,
  `id_nhan_vien` int(11) DEFAULT NULL,
  `ghi_chu` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_hoa_don` (`ma_hoa_don`),
  KEY `id_nhan_vien` (`id_nhan_vien`),
  CONSTRAINT `hoa_don_ibfk_1` FOREIGN KEY (`id_nhan_vien`) REFERENCES `tai_khoan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- Tạo bảng chi tiết hóa đơn
CREATE TABLE IF NOT EXISTS `chi_tiet_hoa_don` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_hoa_don` int(11) NOT NULL,
  `id_san_pham` int(11) NOT NULL,
  `ten_san_pham` varchar(255) NOT NULL,
  `don_gia` decimal(15,2) NOT NULL,
  `so_luong` int(11) NOT NULL,
  `thanh_tien` decimal(15,2) NOT NULL,
  `giam_gia` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `id_hoa_don` (`id_hoa_don`),
  KEY `id_san_pham` (`id_san_pham`),
  CONSTRAINT `chi_tiet_hoa_don_ibfk_1` FOREIGN KEY (`id_hoa_don`) REFERENCES `hoa_don` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chi_tiet_hoa_don_ibfk_2` FOREIGN KEY (`id_san_pham`) REFERENCES `san_pham` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
