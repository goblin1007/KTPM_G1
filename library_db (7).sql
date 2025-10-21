-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 21, 2025 lúc 05:36 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `library_db`
--

DELIMITER $$
--
-- Thủ tục
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `thucapnhatdocgia` (IN `p_id` INT, IN `p_ten` VARCHAR(255), IN `p_namsinh` INT, IN `p_sodt` CHAR(10))   BEGIN
  IF NOT EXISTS (SELECT 1 FROM docgia WHERE id = p_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ID độc giả không tồn tại';
  END IF;
  UPDATE docgia SET ten=p_ten, namsinh=p_namsinh, sodienthoai=p_sodt WHERE id=p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thucapnhatsach` (IN `p_id` INT, IN `p_ten` VARCHAR(255), IN `p_tacgia` VARCHAR(255), IN `p_namxuatban` INT, IN `p_giabia` BIGINT, IN `p_soluong` INT)   BEGIN
  IF NOT EXISTS (SELECT 1 FROM sach WHERE id = p_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ID sách không tồn tại';
  END IF;
  UPDATE sach SET ten=p_ten, tacgia=p_tacgia, namxuatban=p_namxuatban, giabia=p_giabia, soluong=p_soluong
    WHERE id=p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thucmuonsach` (IN `p_madocgia` INT, IN `p_masach` INT, IN `p_soluong` INT, IN `p_ngaymuon` DATE, IN `p_ghichu` VARCHAR(500))   BEGIN
  DECLARE v_soluong_hientai INT;
  DECLARE v_hantra DATE;
  
  SELECT soluong INTO v_soluong_hientai FROM sach WHERE id = p_masach;
  IF v_soluong_hientai < p_soluong THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Không đủ sách để mượn';
  END IF;
  
  SET v_hantra = DATE_ADD(p_ngaymuon, INTERVAL 14 DAY);

  -- INSERT chỉ đưa soluongmuon (không còn soluongtra)
  INSERT INTO muonsach (madocgia, masach, soluongmuon, ngaymuon, hantra, trangthai, phiphat, ghichu)
  VALUES (p_madocgia, p_masach, p_soluong, p_ngaymuon, v_hantra, 'Đang mượn', 0, p_ghichu);
  
  UPDATE sach SET soluong = soluong - p_soluong WHERE id = p_masach;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thucthemdocgia` (IN `p_id` INT, IN `p_ten` VARCHAR(255), IN `p_namsinh` INT, IN `p_sodt` CHAR(10))   BEGIN
  IF EXISTS (SELECT 1 FROM docgia WHERE id = p_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ID độc giả đã tồn tại';
  END IF;
  INSERT INTO docgia(id,ten,namsinh,sodienthoai) VALUES(p_id,p_ten,p_namsinh,p_sodt);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thucthemsach` (IN `p_id` INT, IN `p_ten` VARCHAR(255), IN `p_tacgia` VARCHAR(255), IN `p_namxuatban` INT, IN `p_giabia` BIGINT, IN `p_soluong` INT)   BEGIN
  IF EXISTS (SELECT 1 FROM sach WHERE id = p_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ID sách đã tồn tại';
  END IF;
  INSERT INTO sach(id,ten,tacgia,namxuatban,giabia,soluong)
    VALUES(p_id,p_ten,p_tacgia,p_namxuatban,p_giabia,p_soluong);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thuctrasach` (IN `p_mamuon` INT, IN `p_soluongtra` INT, IN `p_ngaytra` DATE, IN `p_ghichu` VARCHAR(500))   BEGIN
  DECLARE v_madocgia INT;
  DECLARE v_masach INT;
  DECLARE v_soluongmuon INT;
  DECLARE v_total_returned INT DEFAULT 0;
  DECLARE v_last_return DATE;
  DECLARE v_giabia INT DEFAULT 0;
  DECLARE v_ngaytre INT DEFAULT 0;
  DECLARE v_phiphat INT DEFAULT 0;
  DECLARE v_conlai INT;
  DECLARE v_ghichu_cu VARCHAR(1000);
  DECLARE v_ghichu_new VARCHAR(1000);

  -- Lấy thông tin phiếu mượn
  SELECT madocgia, masach, soluongmuon, hantra, ghichu
    INTO v_madocgia, v_masach, v_soluongmuon, v_last_return, v_ghichu_cu
  FROM muonsach
  WHERE id = p_mamuon
  LIMIT 1;

  IF v_madocgia IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Không tìm thấy phiếu mượn';
  END IF;

  -- Tổng số đã trả trước đó cho phiếu này
  SELECT IFNULL(SUM(soluongtra), 0) INTO v_total_returned
  FROM trasach
  WHERE mamuon = p_mamuon;

  -- Kiểm tra trả quá số
  SET v_conlai = v_soluongmuon - v_total_returned - p_soluongtra;
  IF v_conlai < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số lượng trả vượt quá số lượng mượn';
  END IF;

  -- Lấy giá bìa (nếu cần để tính phạt/mất sách)
  SELECT giabia INTO v_giabia FROM sach WHERE id = v_masach LIMIT 1;

  -- Tính phạt: nếu trả trễ thì 2000đ/ngày/quyển (theo quy định hiện tại)
  -- Tìm hạn trả trong muonsach (đã lấy vào v_last_return above? careful: v_last_return currently holds hantra)
  SELECT hantra INTO v_last_return FROM muonsach WHERE id = p_mamuon LIMIT 1;

  IF p_ngaytra > v_last_return THEN
    SET v_ngaytre = DATEDIFF(p_ngaytra, v_last_return);
    SET v_phiphat = v_ngaytre * p_soluongtra * 2000;
  ELSE
    SET v_ngaytre = 0;
    SET v_phiphat = 0;
  END IF;

  -- Chuẩn bị ghi ghichu mới (nối ghichu cũ + ghichu trả)
  IF p_ghichu IS NOT NULL AND TRIM(p_ghichu) <> '' THEN
    IF v_ghichu_cu IS NOT NULL AND TRIM(v_ghichu_cu) <> '' THEN
      SET v_ghichu_new = CONCAT(v_ghichu_cu, ' | Trả: ', p_ghichu);
    ELSE
      SET v_ghichu_new = CONCAT('Trả: ', p_ghichu);
    END IF;
  ELSE
    SET v_ghichu_new = v_ghichu_cu;
  END IF;

  -- Chèn bản ghi vào bảng trasach
  INSERT INTO trasach (madocgia, masach, soluongtra, ngaytrathucte, trangthai, phiphat, ghichu, mamuon)
  VALUES (v_madocgia, v_masach, p_soluongtra, p_ngaytra,
          CASE WHEN p_ngaytra > v_last_return THEN 'Trả trễ hạn' ELSE 'Trả đúng hạn' END,
          v_phiphat, v_ghichu_new, p_mamuon);

  -- Cập nhật số lượng sách trong kho
  UPDATE sach
  SET soluong = soluong + p_soluongtra
  WHERE id = v_masach;

  -- Cập nhật lại tổng đã trả, ngày trả thực tế, phạt và trạng thái trong muonsach
  -- (tính lại từ trasach để đảm bảo chính xác)
  UPDATE muonsach m
  LEFT JOIN (
    SELECT mamuon, SUM(soluongtra) AS total_returned, MAX(ngaytrathucte) AS last_return, SUM(phiphat) AS total_phiphat
    FROM trasach
    WHERE mamuon = p_mamuon
    GROUP BY mamuon
  ) t ON t.mamuon = m.id
  SET
    m.ngaytrathucte = COALESCE(t.last_return, m.ngaytrathucte),
    m.phiphat = COALESCE(m.phiphat,0) + COALESCE(t.total_phiphat,0) - COALESCE(m.phiphat,0), -- đảm bảo cộng dồn đúng
    m.ghichu = COALESCE(t.total_returned,0) + 0, -- placeholder nếu muốn lưu tổng (hoặc set v_ghichu_new)
    m.ghichu = v_ghichu_new,
    m.trangthai = CASE
      WHEN COALESCE(t.total_returned,0) >= m.soluongmuon AND t.last_return IS NOT NULL
        THEN CASE WHEN t.last_return > m.hantra THEN 'Trả trễ hạn' ELSE 'Trả đúng hạn' END
      ELSE 'Đang mượn'
    END
  WHERE m.id = p_mamuon;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thudsmuonchocdg` (IN `p_madg` INT)   BEGIN
  SELECT 
    m.id AS mamuon,
    m.ngaymuon,
    m.hantra,
    m.masach,
    s.ten AS tensach,
    m.soluongmuon,
    -- tổng đã trả từ trasach
    (m.soluongmuon - IFNULL((SELECT SUM(t.soluongtra) FROM trasach t WHERE t.mamuon = m.id),0)) AS conlai
  FROM muonsach m
  JOIN sach s ON m.masach = s.id
  WHERE m.madocgia = p_madg
    AND (m.soluongmuon - IFNULL((SELECT SUM(t2.soluongtra) FROM trasach t2 WHERE t2.mamuon = m.id),0)) > 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thulaysach` (IN `p_id` INT)   BEGIN
  SELECT id, ten, tacgia, namxuatban, giabia, soluong
  FROM sach
  WHERE id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thuxoadocgia` (IN `p_id` INT)   BEGIN
  -- Kiểm tra xem độc giả có phiếu mượn chưa trả hết không
  IF EXISTS (
    SELECT 1
    FROM muonsach m
    LEFT JOIN (
      SELECT mamuon, SUM(soluongtra) AS total_returned
      FROM trasach
      GROUP BY mamuon
    ) t ON t.mamuon = m.id
    WHERE m.madocgia = p_id 
      AND (m.soluongmuon - IFNULL(t.total_returned, 0)) > 0
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Không thể xóa: độc giả còn sách chưa trả';
  END IF;
  
  DELETE FROM docgia WHERE id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `thuxoasach` (IN `p_id` INT)   BEGIN
  IF EXISTS (
    SELECT 1
    FROM muonsach m
    LEFT JOIN (
      SELECT mamuon, SUM(soluongtra) AS total_returned
      FROM trasach
      GROUP BY mamuon
    ) t ON t.mamuon = m.id
    WHERE m.masach = p_id 
      AND (m.soluongmuon - IFNULL(t.total_returned, 0)) > 0
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Không thể xóa: sách đang được mượn';
  END IF;
  
  DELETE FROM sach WHERE id = p_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `docgia`
--

CREATE TABLE `docgia` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `namsinh` int(11) DEFAULT NULL,
  `sodienthoai` char(10) DEFAULT NULL,
  `tao_luc` timestamp NOT NULL DEFAULT current_timestamp(),
  `cap_nhat_luc` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `docgia`
--

INSERT INTO `docgia` (`id`, `ten`, `namsinh`, `sodienthoai`, `tao_luc`, `cap_nhat_luc`) VALUES
(1, 'Nguyễn Thị Lan', 1998, '0912345678', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(2, 'Trần Văn Hùng', 1995, '0987654321', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(3, 'Lê Thu Hằng', 2000, '0901122334', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(4, 'Phạm Minh Tuấn', 1988, '0977554433', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(5, 'Hoàng Anh Thư', 1999, '0966778899', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(6, 'Đỗ Quang Khải', 1990, '0933221100', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(7, 'Vũ Thị Mai', 1997, '0944556677', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(8, 'Ngô Văn Phúc', 1992, '0922334455', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(9, 'Bùi Thanh Tâm', 2001, '0998877665', '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(10, 'Đặng Thị Hồng', 1989, '0909090909', '2025-10-19 15:02:27', '2025-10-21 15:32:05');

--
-- Bẫy `docgia`
--
DELIMITER $$
CREATE TRIGGER `tr_docgia_before_delete` BEFORE DELETE ON `docgia` FOR EACH ROW BEGIN
  -- Kiểm tra xem độc giả có phiếu mượn chưa trả hết không
  IF EXISTS (
    SELECT 1
    FROM muonsach m
    LEFT JOIN (
      SELECT mamuon, SUM(soluongtra) AS total_returned
      FROM trasach
      GROUP BY mamuon
    ) t ON t.mamuon = m.id
    WHERE m.madocgia = OLD.id 
      AND (m.soluongmuon - IFNULL(t.total_returned, 0)) > 0
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Không thể xóa độc giả: còn phiếu mượn chưa trả';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_docgia_before_ins` BEFORE INSERT ON `docgia` FOR EACH ROW BEGIN
  IF NEW.id IS NULL OR NEW.id < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ID độc giả phải là số nguyên dương';
  END IF;
  IF TRIM(NEW.ten) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên độc giả không được để trống';
  END IF;
  IF NOT (NEW.ten RLIKE '^[A-Za-zÀ-ỹ ]+$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên độc giả chỉ chứa chữ';
  END IF;
  IF NEW.namsinh < 1960 OR NEW.namsinh > 2007 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Năm sinh phải trong khoảng 1960-2007';
  END IF;
  IF NOT (NEW.sodienthoai RLIKE '^0[0-9]{9}$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số điện thoại phải đúng 10 chữ số và bắt đầu 0';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_docgia_before_upd` BEFORE UPDATE ON `docgia` FOR EACH ROW BEGIN
  -- tương tự
  IF NOT (NEW.sodienthoai RLIKE '^0[0-9]{9}$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số điện thoại không hợp lệ';
  END IF;
  IF NEW.namsinh < 1960 OR NEW.namsinh > 2007 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Năm sinh không hợp lệ';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `muonsach`
--

CREATE TABLE `muonsach` (
  `id` int(11) NOT NULL,
  `madocgia` int(11) NOT NULL,
  `masach` int(11) NOT NULL,
  `ngaymuon` date NOT NULL,
  `hantra` date NOT NULL,
  `ngaytrathucte` date DEFAULT NULL,
  `soluongmuon` int(11) NOT NULL,
  `trangthai` enum('Đang mượn','Trả đúng hạn','Trả trễ hạn','Mất sách') DEFAULT 'Đang mượn',
  `phiphat` bigint(20) DEFAULT 0,
  `ghichu` varchar(255) DEFAULT NULL,
  `tao_luc` timestamp NOT NULL DEFAULT current_timestamp(),
  `cap_nhat_luc` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `muonsach`
--

INSERT INTO `muonsach` (`id`, `madocgia`, `masach`, `ngaymuon`, `hantra`, `ngaytrathucte`, `soluongmuon`, `trangthai`, `phiphat`, `ghichu`, `tao_luc`, `cap_nhat_luc`) VALUES
(1, 1, 1, '2025-10-01', '2025-10-15', '2025-10-20', 2, 'Trả trễ hạn', 95000, NULL, '2025-10-19 15:02:27', '2025-10-19 17:31:48'),
(2, 1, 1, '2025-10-01', '2025-10-15', '2025-10-20', 2, 'Trả trễ hạn', 20000, NULL, '2025-10-19 15:06:21', '2025-10-19 17:32:05'),
(3, 1, 2, '2025-10-20', '2025-11-03', '2025-10-20', 2, 'Trả đúng hạn', 0, NULL, '2025-10-19 17:38:43', '2025-10-19 17:42:28'),
(4, 1, 2, '2025-10-20', '2025-11-03', '2025-10-25', 2, 'Trả đúng hạn', 0, NULL, '2025-10-19 17:55:21', '2025-10-20 09:06:19'),
(5, 1, 2, '2025-10-20', '2025-11-03', '2025-10-20', 2, 'Đang mượn', 0, 'Trả: Trả 1 quyển test', '2025-10-19 17:55:34', '2025-10-20 15:20:22'),
(6, 5, 2, '2025-10-20', '2025-11-03', NULL, 2, 'Đang mượn', 0, NULL, '2025-10-19 17:55:44', '2025-10-19 17:55:44'),
(7, 5, 7, '2025-10-20', '2025-11-03', NULL, 2, 'Đang mượn', 0, NULL, '2025-10-19 17:56:00', '2025-10-19 17:56:00'),
(8, 1, 2, '2025-10-20', '2025-11-03', NULL, 3, 'Đang mượn', 0, NULL, '2025-10-20 09:06:19', '2025-10-20 09:06:19'),
(9, 4, 2, '2025-10-20', '2025-11-03', '2025-10-20', 3, 'Trả đúng hạn', 0, 'hihihaha', '2025-10-20 13:22:19', '2025-10-20 15:20:22'),
(10, 3, 2, '2025-10-20', '2025-11-03', '2025-10-20', 3, 'Trả đúng hạn', 0, 'hahah | Trả: rách bìa nè', '2025-10-20 14:00:17', '2025-10-20 15:20:22'),
(11, 6, 2, '2025-10-20', '2025-11-03', '2025-10-20', 3, 'Trả đúng hạn', 0, 'gfsha', '2025-10-20 15:27:23', '2025-10-20 16:07:11'),
(12, 3, 2, '2025-10-20', '2025-11-03', '2025-10-20', 4, 'Trả đúng hạn', 0, 'thật không', '2025-10-20 16:07:38', '2025-10-20 16:08:09'),
(13, 3, 1, '2025-10-21', '2025-11-04', '2025-10-21', 3, 'Trả đúng hạn', 0, 'thử', '2025-10-21 15:32:33', '2025-10-21 15:32:56');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sach`
--

CREATE TABLE `sach` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `tacgia` varchar(255) NOT NULL,
  `namxuatban` int(11) NOT NULL,
  `giabia` bigint(20) NOT NULL,
  `soluong` int(11) NOT NULL DEFAULT 0,
  `tao_luc` timestamp NOT NULL DEFAULT current_timestamp(),
  `cap_nhat_luc` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sach`
--

INSERT INTO `sach` (`id`, `ten`, `tacgia`, `namxuatban`, `giabia`, `soluong`, `tao_luc`, `cap_nhat_luc`) VALUES
(1, 'Lập trình C cơ bản', 'Nguyễn Văn An', 2019, 85000, 100, '2025-10-19 15:02:27', '2025-10-21 15:32:56'),
(2, 'Nhập môn Java', 'Trần Thị Hoa', 2021, 120000, 94, '2025-10-19 15:02:27', '2025-10-20 16:08:09'),
(3, 'Python cho người mới bắt đầu', 'Lê Minh Quân', 2020, 150000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(4, 'Cấu trúc dữ liệu và giải thuật', 'Phạm Ngọc Thạch', 2018, 135000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(5, 'English Grammar in Use', 'Raymond Murphy', 2018, 200000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(6, 'Từ điển Anh – Việt', 'NXB Giáo dục', 2017, 95000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(7, 'Tâm lý học đại cương', 'Phạm Thu Trang', 2016, 110000, 98, '2025-10-19 15:02:27', '2025-10-19 17:56:00'),
(8, 'Hành vi tổ chức', 'Đỗ Hải Yến', 2022, 125000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(9, 'Marketing căn bản', 'Philip Kotler', 2015, 180000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27'),
(10, 'Quản trị học', 'Nguyễn Văn Bình', 2022, 160000, 100, '2025-10-19 15:02:27', '2025-10-19 15:02:27');

--
-- Bẫy `sach`
--
DELIMITER $$
CREATE TRIGGER `tr_sach_before_delete` BEFORE DELETE ON `sach` FOR EACH ROW BEGIN
  IF EXISTS (
    SELECT 1
    FROM muonsach m
    LEFT JOIN (
      SELECT mamuon, SUM(soluongtra) AS total_returned
      FROM trasach
      GROUP BY mamuon
    ) t ON t.mamuon = m.id
    WHERE m.masach = OLD.id 
      AND (m.soluongmuon - IFNULL(t.total_returned, 0)) > 0
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Không thể xóa sách: sách đang được mượn';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_sach_before_ins` BEFORE INSERT ON `sach` FOR EACH ROW BEGIN
  -- ID sách: >=1
  IF NEW.id IS NULL OR NEW.id < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ID sách phải là số nguyên dương';
  END IF;
  -- Tên sách: không rỗng và cho phép chữ + số + khoảng trắng
  IF TRIM(NEW.ten) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên sách không được để trống';
  END IF;
  IF NOT (NEW.ten RLIKE '^[A-Za-zÀ-ỹ0-9 ]+$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên sách chỉ chứa chữ, số và khoảng trắng';
  END IF;
  -- Tác giả: chỉ chữ (không chứa số)
  IF NOT (NEW.tacgia RLIKE '^[A-Za-zÀ-ỹ ]+$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên tác giả chỉ chứa chữ';
  END IF;
  -- Năm xuất bản: 1900..2025
  IF NEW.namxuatban < 1900 OR NEW.namxuatban > 2025 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Năm xuất bản phải trong khoảng 1900-2025';
  END IF;
  -- Giá bìa: 10000..1000000
  IF NEW.giabia < 10000 OR NEW.giabia > 1000000 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá bìa phải trong khoảng 10000-1000000';
  END IF;
  -- Số lượng: >=1
  IF NEW.soluong < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số lượng phải >= 1';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_sach_before_upd` BEFORE UPDATE ON `sach` FOR EACH ROW BEGIN
  -- Tên sách: không rỗng và cho phép chữ + số + khoảng trắng
  IF TRIM(NEW.ten) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên sách không được để trống';
  END IF;
  IF NOT (NEW.ten RLIKE '^[A-Za-zÀ-ỹ0-9 ]+$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên sách chỉ chứa chữ, số và khoảng trắng';
  END IF;
  -- Tác giả: vẫn chỉ chữ
  IF TRIM(NEW.tacgia) = '' OR NOT (NEW.tacgia RLIKE '^[A-Za-zÀ-ỹ ]+$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên tác giả không hợp lệ';
  END IF;
  IF NEW.namxuatban < 1900 OR NEW.namxuatban > 2025 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Năm xuất bản phải trong khoảng 1900-2025';
  END IF;
  IF NEW.giabia < 10000 OR NEW.giabia > 1000000 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá bìa không hợp lệ';
  END IF;
  IF NEW.soluong < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số lượng không hợp lệ';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `trasach`
--

CREATE TABLE `trasach` (
  `id` int(11) NOT NULL,
  `madocgia` int(11) NOT NULL,
  `masach` int(11) NOT NULL,
  `soluongtra` int(11) NOT NULL,
  `ngaytrathucte` date NOT NULL,
  `trangthai` enum('Trả đúng hạn','Trả trễ hạn','Mất sách') NOT NULL,
  `phiphat` bigint(20) NOT NULL DEFAULT 0,
  `ghichu` varchar(255) DEFAULT NULL,
  `tao_luc` timestamp NOT NULL DEFAULT current_timestamp(),
  `mamuon` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `trasach`
--

INSERT INTO `trasach` (`id`, `madocgia`, `masach`, `soluongtra`, `ngaytrathucte`, `trangthai`, `phiphat`, `ghichu`, `tao_luc`, `mamuon`) VALUES
(1, 1, 1, 1, '2025-10-10', 'Mất sách', 85000, NULL, '2025-10-19 15:02:27', 1),
(2, 1, 1, 1, '2025-10-20', 'Trả trễ hạn', 10000, NULL, '2025-10-19 17:31:48', 1),
(3, 1, 1, 2, '2025-10-20', 'Trả trễ hạn', 20000, NULL, '2025-10-19 17:32:05', 2),
(4, 1, 2, 2, '2025-10-20', 'Trả đúng hạn', 0, NULL, '2025-10-19 17:42:28', 3),
(5, 1, 2, 2, '2025-10-25', 'Trả đúng hạn', 0, NULL, '2025-10-20 09:06:19', 4),
(6, 1, 2, 1, '2025-10-20', 'Trả đúng hạn', 0, 'Trả: Trả 1 quyển test', '2025-10-20 13:59:13', 5),
(7, 4, 2, 3, '2025-10-20', 'Trả đúng hạn', 0, 'hihihaha', '2025-10-20 13:59:35', 9),
(8, 3, 2, 3, '2025-10-20', 'Trả đúng hạn', 0, 'hahah | Trả: rách bìa nè', '2025-10-20 14:00:43', 10),
(9, 6, 2, 3, '2025-10-20', 'Trả đúng hạn', 0, 'gfsha', '2025-10-20 16:07:11', 11),
(10, 3, 2, 4, '2025-10-20', 'Trả đúng hạn', 0, 'thật không', '2025-10-20 16:08:09', 12),
(11, 3, 1, 3, '2025-10-21', 'Trả đúng hạn', 0, 'thử', '2025-10-21 15:32:56', 13);

--
-- Bẫy `trasach`
--
DELIMITER $$
CREATE TRIGGER `tr_trasach_before_ins` BEFORE INSERT ON `trasach` FOR EACH ROW BEGIN
  DECLARE v_borrowdate DATE;
  DECLARE v_total_borrowed INT;
  DECLARE v_total_already_returned INT;

  -- Chỉ cho phép insert qua procedure
  IF @ALLOW_TRASACH_INSERT IS NULL OR @ALLOW_TRASACH_INSERT <> 1 THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Không được INSERT trực tiếp vào trasach. Hãy dùng thủ tục thuctrasach.';
  END IF;

  -- Lấy thông tin từ bảng muonsach
  SELECT ngaymuon, soluongmuon
  INTO v_borrowdate, v_total_borrowed
  FROM muonsach
  WHERE id = NEW.mamuon
  LIMIT 1;

  IF v_borrowdate IS NULL THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Phiếu mượn không tồn tại (trasach)';
  END IF;

  -- Tính tổng số lượng đã trả từ bảng trasach
  SELECT IFNULL(SUM(soluongtra), 0) INTO v_total_already_returned
  FROM trasach
  WHERE mamuon = NEW.mamuon;

  -- Kiểm tra ngày trả
  IF NEW.ngaytrathucte < v_borrowdate THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Ngày trả không thể trước ngày mượn';
  END IF;

  IF YEAR(NEW.ngaytrathucte) < 2025 THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Năm ngày trả phải >= 2025';
  END IF;

  -- Kiểm tra số lượng trả hợp lệ
  IF NEW.soluongtra < 0 OR (v_total_already_returned + NEW.soluongtra) > v_total_borrowed THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Số lượng trả trong trasach vượt quá phần còn lại';
  END IF;

  -- Kiểm tra trạng thái
  IF NOT (NEW.trangthai IN ('Trả đúng hạn','Trả trễ hạn','Mất sách')) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Trạng thái trả không hợp lệ';
  END IF;
END
$$
DELIMITER ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `docgia`
--
ALTER TABLE `docgia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_docgia_sodt` (`sodienthoai`),
  ADD KEY `idx_docgia_ten` (`ten`);

--
-- Chỉ mục cho bảng `muonsach`
--
ALTER TABLE `muonsach`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_madg_masach` (`madocgia`,`masach`),
  ADD KEY `fk_muonsach_sach` (`masach`),
  ADD KEY `idx_muonsach_trangthai` (`trangthai`),
  ADD KEY `idx_muonsach_ngaymuon` (`ngaymuon`);

--
-- Chỉ mục cho bảng `sach`
--
ALTER TABLE `sach`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sach_ten` (`ten`);

--
-- Chỉ mục cho bảng `trasach`
--
ALTER TABLE `trasach`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trasach_madg` (`madocgia`),
  ADD KEY `fk_trasach_sach` (`masach`),
  ADD KEY `idx_trasach_mamuon` (`mamuon`),
  ADD KEY `idx_trasach_ngaytra` (`ngaytrathucte`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `muonsach`
--
ALTER TABLE `muonsach`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `trasach`
--
ALTER TABLE `trasach`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `muonsach`
--
ALTER TABLE `muonsach`
  ADD CONSTRAINT `fk_muonsach_docgia` FOREIGN KEY (`madocgia`) REFERENCES `docgia` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_muonsach_sach` FOREIGN KEY (`masach`) REFERENCES `sach` (`id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `trasach`
--
ALTER TABLE `trasach`
  ADD CONSTRAINT `fk_trasach_docgia` FOREIGN KEY (`madocgia`) REFERENCES `docgia` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_trasach_mamuon` FOREIGN KEY (`mamuon`) REFERENCES `muonsach` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_trasach_sach` FOREIGN KEY (`masach`) REFERENCES `sach` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
