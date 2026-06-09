-- Tạo bảng khách hàng
CREATE TABLE IF NOT EXISTS `khach_hang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ma_khach_hang` varchar(20) DEFAULT NULL,
  `ten_khach_hang` varchar(100) NOT NULL,
  `so_dien_thoai` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `ngay_sinh` date DEFAULT NULL,
  `diem_tich_luy` int(11) DEFAULT 0,
  `ngay_tao` datetime DEFAULT current_timestamp(),
  `ghi_chu` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_khach_hang` (`ma_khach_hang`),
  UNIQUE KEY `so_dien_thoai` (`so_dien_thoai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- Thêm khách hàng mẫu
INSERT INTO `khach_hang` (`ma_khach_hang`, `ten_khach_hang`, `so_dien_thoai`, `diem_tich_luy`) VALUES
('KH001', 'Khách lẻ', NULL, 0);
