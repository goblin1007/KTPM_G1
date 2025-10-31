<?php
include 'db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$message = "";

// ================= VALIDATION =================
function valid_id($s){ return preg_match('/^[0-9]+$/', $s); }
function only_letters_spaces($s){ return preg_match('/^[\p{L} ]+$/u', $s); }
function valid_phone($s){ return preg_match('/^0[0-9]{9}$/', $s); }
function valid_book_name($s){ return preg_match('/^[\p{L}0-9 ]+$/u', $s); }

// Xác định form nào đang active
$active_form = 'list-books'; // mặc định hiển thị danh sách sách

// ========== XÓA SÁCH ==========
if (isset($_GET['delete_book'])) {
    $active_form = 'list-books';
    $id = (int)$_GET['delete_book'];
    try {
        $stmt = $conn->prepare("CALL thuxoasach(?)");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $message = "✅ Xóa sách thành công!";
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $message = "❌ Lỗi: " . $e->getMessage();
    }
}

// ========== XÓA ĐỘC GIẢ ==========
if (isset($_GET['delete_reader'])) {
    $active_form = 'list-readers';
    $id = (int)$_GET['delete_reader'];
    try {
        $stmt = $conn->prepare("CALL thuxoadocgia(?)");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $message = "✅ Xóa độc giả thành công!";
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $message = "❌ Lỗi: " . $e->getMessage();
    }
}

// ======== Xử lý thêm sách ========
if (isset($_POST['add_book'])) {
    $active_form = 'list-books';
    $id       = (int)$_POST['book_id'];
    $name     = trim($_POST['book_name']);
    $author   = trim($_POST['author']);
    $year     = (int)$_POST['publish_year'];
    $price    = (int)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $count = 0; // Khởi tạo mặc định
    // Kiểm tra ID đã tồn tại
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM sach WHERE id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
    }
    if ($count > 0) {
        $message = "❌ ID sách đã tồn tại";
    }
    elseif ($id <= 0) {
        $message = "❌ ID sách không hợp lệ";
    }
    elseif (!valid_book_name($name)) {
        $message = "❌ Tên sách không hợp lệ";
    }
    elseif (!only_letters_spaces($author)) {
        $message = "❌ Tên tác giả không hợp lệ";
    }
    elseif ($year < 1900 || $year > 2025) {
        $message = "❌ Năm xuất bản không hợp lệ";
    }
    elseif ($price < 10000 || $price > 1000000) {
        $message = "❌ Giá bìa không hợp lệ";
    }
    elseif ($quantity < 1) {
        $message = "❌ Số lượng không hợp lệ";
    }
    else {
        $stmt = $conn->prepare("CALL thuchemsach(?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issiii", $id, $name, $author, $year, $price, $quantity);
            if ($stmt->execute()) {
                $message = "✅ Thêm mới thành công!";
            }
            $stmt->close();
        }
    }
}


// ========== Xử lý thêm độc giả ==========
if (isset($_POST['add_reader'])) {
    $active_form = 'list-readers';
    $id = (int)$_POST['reader_id'];
    $name = trim($_POST['reader_name']);
    $birth_year = (int)$_POST['birth_year'];
    $phone = isset($_POST['reader_phone']) ? trim($_POST['reader_phone']) : '';
    
    $count = 0; // Thêm dòng này
    // Kiểm tra ID đã tồn tại
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM docgia WHERE id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
    }
    if ($count > 0) {
        $message = "❌ ID độc giả đã tồn tại";
    }
    elseif ($id < 1) {
        $message = "❌ ID độc giả không hợp lệ!";
    }
    elseif (empty($name)) {
        $message = "❌ Tên độc giả không hợp lệ!";
    }
    elseif (!only_letters_spaces($name)) {
        $message = "❌ Tên độc giả không hợp lệ!";
    }
    elseif ($birth_year < 1960 || $birth_year > 2007) {
        $message = "❌ Năm sinh không hợp lệ";
    }
    elseif (!valid_phone($phone)) {
        $message = "❌ Số điện thoại không hợp lệ";
    }
    else {
        $stmt = $conn->prepare("CALL thucthemdocgia(?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isis", $id, $name, $birth_year, $phone);
            $stmt->execute();
            $stmt->close();
            $message = "✅ Thêm độc giả thành công!";
        }
    }
}

// ========== Xử lý cập nhật độc giả ==========
if (isset($_POST['update_reader'])) {
    $active_form = 'list-readers';
    $id = (int)$_POST['reader_id_update'];
    $name = trim($_POST['reader_name_update']);
    $birth_year = (int)$_POST['birth_year_update'];
    $phone = trim($_POST['reader_phone_update']);

    if ($id < 1) $message = "❌ ID độc giả không hợp lệ!";
    elseif (!only_letters_spaces($name)) $message = "❌ Tên độc giả không hợp lệ!";
    elseif ($birth_year < 1960 || $birth_year > 2007) $message = "❌ Năm sinh không hợp lệ!";
    elseif (!valid_phone($phone)) $message = "❌ Số điện thoại không hợp lệ!";
    else {
        try {
            $stmt = $conn->prepare("CALL thucapnhatdocgia(?, ?, ?, ?)");
            $stmt->bind_param("isis", $id, $name, $birth_year, $phone);
            $stmt->execute();
            $message = "✅ Cập nhật độc giả thành công!";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
        }
    }
}

// ========== Xử lý mượn sách ==========
if (isset($_POST['borrow_book'])) {
    $active_form = 'borrow-book';
    $reader_id = (int)$_POST['reader_id'];
    $book_id = (int)$_POST['book_id'];
    $quantity = (int)$_POST['borrow_quantity'];
    $borrow_date = trim($_POST['borrow_date']);
    $borrow_note = trim($_POST['borrow_note'] ?? '');

    if ($reader_id < 1) {
        $message = "❌ ID độc giả không hợp lệ!";
    }
    elseif ($book_id < 1) {
        $message = "❌ ID sách không hợp lệ!";
    }
    elseif ($quantity < 1 || $quantity > 5) {
        $message = "❌ Số lượng mượn không hợp lệ!";
    }
    elseif (empty($borrow_date)) {
        $message = "❌ Ngày mượn không hợp lệ!";
    }
    else {
        // ✅ KIỂM TRA ĐỘC GIẢ TỒN TẠI
        $check_reader = $conn->prepare("SELECT id FROM docgia WHERE id = ?");
        $check_reader->bind_param("i", $reader_id);
        $check_reader->execute();
        $reader_exists = $check_reader->get_result()->num_rows > 0;
        $check_reader->close();
        
        if (!$reader_exists) {
            $message = "❌ ID độc giả không tồn tại!";
        } else {
            // ✅ KIỂM TRA SÁCH TỒN TẠI VÀ SỐ LƯỢNG
            $check_book = $conn->prepare("SELECT id, ten, soluong FROM sach WHERE id = ?");
            $check_book->bind_param("i", $book_id);
            $check_book->execute();
            $book_result = $check_book->get_result();
            
            if ($book_result->num_rows === 0) {
                $message = "❌ ID sách không tồn tại!";
                $check_book->close();
            } else {
                $book_info = $book_result->fetch_assoc();
                $check_book->close();
                
                // ✅ KIỂM TRA SỐ LƯỢNG SÁCH CÒN ĐỦ KHÔNG
                if ($book_info['soluong'] < $quantity) {
                    $message = "❌ Sách '{$book_info['ten']}' chỉ còn {$book_info['soluong']} cuốn, không đủ để mượn {$quantity} cuốn!";
                } else {
                    // ✅ TẤT CẢ HỢP LỆ → GỌI STORED PROCEDURE
                    try {
                        $stmt = $conn->prepare("CALL thucmuonsach(?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiiss", $reader_id, $book_id, $quantity, $borrow_date, $borrow_note);
                        $stmt->execute();
                        $message = "✅ Mượn sách thành công!";
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        $message = "❌ Lỗi: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// ========== Xử lý cập nhật sách ==========
$update_book_data = null;

if (isset($_POST['update_book'])) {
    $active_form = 'list-books'; 
    $id = (int)$_POST['book_id_update'];
    $name = trim($_POST['book_name_update']);
    $author = trim($_POST['author_update']);
    $year = (int)$_POST['publish_year_update'];
    $price = (int)$_POST['price_update'];
    $quantity = (int)$_POST['quantity_update'];

    $update_book_data = [
        'id' => $id,
        'name' => $name,
        'author' => $author,
        'year' => $year,
        'price' => $price,
        'quantity' => $quantity
    ];

    if ($id < 1) {
        $message = "❌ ID sách không hợp lệ!";
    }
    elseif (!valid_book_name($name)) {
        $message = "❌ Tên sách không hợp lệ!";
    }
    elseif (!only_letters_spaces($author)) {
        $message = "❌ Tên tác giả không hợp lệ!";
    }
    elseif ($year < 1900 || $year > 2025) {
        $message = "❌ Năm xuất bản không hợp lệ!";
    }
    elseif ($price < 10000 || $price > 1000000) {
        $message = "❌ Giá bìa không hợp lệ!";
    }
    elseif ($quantity < 1) {
        $message = "❌ Số lượng không hợp lệ!";
    }
    else {
        try {
            $stmt = $conn->prepare("CALL thucapnhatsach(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiii", $id, $name, $author, $year, $price, $quantity);
            $stmt->execute();
            $message = "✅ Cập nhật sách thành công!";
            $stmt->close();
            $update_book_data = null;
        } catch (mysqli_sql_exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
        }
    }
}

// ========== Xử lý tìm độc giả để trả sách ==========
$reader_info = null;
$borrow_list = [];
if (isset($_POST['find_reader'])) {
    $active_form = 'return-book';
    $reader_id = (int)$_POST['reader_id_find'];
    
    if ($reader_id < 1) {
        $message = "❌ ID độc giả không hợp lệ!";
    } else {
        $stmt = $conn->prepare("SELECT id, ten, sodienthoai FROM docgia WHERE id = ?");
        $stmt->bind_param("i", $reader_id);
        $stmt->execute();
        $reader_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$reader_info) {
            $message = "❌ ID độc giả không tồn tại!";
        } else {
            $stmt = $conn->prepare("CALL thudsmuonchocdg(?)");
            $stmt->bind_param("i", $reader_id);
            $stmt->execute();
            $borrow_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// ========== Xử lý trả sách ==========
if (isset($_POST['return_book_submit'])) {
    $active_form = 'return-book';
    $borrow_id = (int)$_POST['borrow_id'];
    $quantity = (int)$_POST['quantity_return'];
    $return_date = trim($_POST['return_date']);
    $return_note = trim($_POST['return_note'] ?? '');

     // Kiểm tra ngày trả hợp lệ
    if (empty($return_date) || strtotime($return_date) === false) {
        $message = "❌ Ngày trả không hợp lệ!";
    } 
    elseif (strtotime($return_date) < strtotime('2025-01-01')) {
        $message = "❌ Ngày trả không hợp lệ!";
    }
    else {

    // Kiểm tra phiếu mượn và số lượng còn lại
    $stmt = $conn->prepare("SELECT m.soluongmuon, 
                           (SELECT COALESCE(SUM(soluongtra), 0) 
                            FROM trasach 
                            WHERE mamuon = m.id) as soluongtra, 
                           s.ten as tensach 
                           FROM muonsach m 
                           JOIN sach s ON m.masach = s.id 
                           WHERE m.id = ?");
    $stmt->bind_param("i", $borrow_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
   // ========== Xử lý trả sách ==========
if (isset($_POST['return_book_submit'])) {
    $active_form = 'return-book';
    $borrow_id = (int)$_POST['borrow_id'];
    $quantity = (int)$_POST['quantity_return'];
    $return_date = trim($_POST['return_date']);
    $return_note = trim($_POST['return_note'] ?? '');

    // Kiểm tra ngày trả hợp lệ
    if (empty($return_date) || strtotime($return_date) === false) {
        $message = "❌ Ngày trả không hợp lệ!";
    } 
    elseif (strtotime($return_date) < strtotime('2025-01-01')) {
        $message = "❌ Ngày trả không hợp lệ!";
    }
    else {
        // Lấy thông tin phiếu mượn
        $stmt = $conn->prepare("
            SELECT m.soluongmuon, m.hantra, m.ngaymuon, m.masach, s.giabia, s.ten as tensach,
                   (SELECT COALESCE(SUM(soluongtra), 0) FROM trasach WHERE mamuon = m.id) as soluongtra
            FROM muonsach m 
            JOIN sach s ON m.masach = s.id 
            WHERE m.id = ?
        ");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            $remaining = $row['soluongmuon'] - $row['soluongtra'];
            $tensach = $row['tensach'];
            $giabia = $row['giabia'];
            $hantra = $row['hantra'];
            $ngaymuon = $row['ngaymuon'];
            
            // Kiểm tra ngày trả không được trước ngày mượn
            if (strtotime($return_date) < strtotime($ngaymuon)) {
                $message = "❌ Ngày trả không hợp lệ!";
            }
            // Kiểm tra số lượng trả hợp lệ
            elseif ($quantity < 0 || $quantity > $remaining) {
                $message = "❌ Số lượng trả không hợp lệ!";
            } 
            else {
                try {
                    $hantra_dt = new DateTime($hantra);
                    $ngaytra_dt = new DateTime($return_date);
                    $phi_tre = 0;
                    $phi_mat = 0;
                    $tong_phi = 0;
                    $detail_msg = "";
                    $days_late = 0;
                    
                    // Tính số ngày trễ (nếu có)
                    if ($ngaytra_dt > $hantra_dt) {
                        $days_late = $ngaytra_dt->diff($hantra_dt)->days;
                    }
                    
                    // TRƯỜNG HỢP 1: MẤT SÁCH (Số lượng trả = 0)
                    if ($quantity == 0) {
                        // 1. Phí mất sách
                        $phi_mat = $giabia * $remaining;
                        
                        // 2. Phí trả trễ (nếu có) - tính cho số lượng bị mất
                        if ($days_late > 0) {
                            $phi_tre = $days_late * $remaining * 2000;
                            $detail_msg = "Trả trễ hạn: Phí phạt = $days_late ngày × 2,000đ × $remaining cuốn = " . number_format($phi_tre) . "đ | ";
                        }
                        
                        $detail_msg .= "Mất sách: Phí đền bù = " . number_format($giabia) . "đ × $remaining cuốn = " . number_format($phi_mat) . "đ";
                        $tong_phi = $phi_tre + $phi_mat;
                        
                        // Gọi SP
                        $conn->query("SET @ALLOW_TRASACH_INSERT = 1");
                        $stmt_return = $conn->prepare("CALL thuctrasach(?, ?, ?, ?)");
                        $stmt_return->bind_param("iiss", $borrow_id, $quantity, $return_date, $return_note);
                        $stmt_return->execute();
                        $stmt_return->close();
                        $conn->query("SET @ALLOW_TRASACH_INSERT = NULL");
                        
                        $message = "⚠️ Ghi nhận mất $remaining cuốn '$tensach' - $detail_msg | Tổng phí phạt: " . number_format($tong_phi) . "đ";
                    }
                    // TRƯỜNG HỢP 2: TRẢ BÌNH THƯỜNG (Số lượng >= 1)
                    else {
                        // Chỉ tính phí trả trễ (nếu có)
                        if ($days_late > 0) {
                            $phi_tre = $days_late * $quantity * 2000;
                            $detail_msg = "Trả trễ hạn: Phí phạt = $days_late ngày × 2,000đ × $quantity cuốn = " . number_format($phi_tre) . "đ";
                        } else {
                            $detail_msg = "Trả đúng hạn: 0đ";
                        }
                        
                        $tong_phi = $phi_tre;
                        
                        // Gọi SP
                        $conn->query("SET @ALLOW_TRASACH_INSERT = 1");
                        $stmt_return = $conn->prepare("CALL thuctrasach(?, ?, ?, ?)");
                        $stmt_return->bind_param("iiss", $borrow_id, $quantity, $return_date, $return_note);
                        $stmt_return->execute();
                        $stmt_return->close();
                        $conn->query("SET @ALLOW_TRASACH_INSERT = NULL");
                        
                        $message = "✅ Đã trả $quantity cuốn '$tensach' - $detail_msg";
                        
                        // Ghi chú nếu trả thiếu
                        if ($quantity < $remaining) {
                            $con_lai = $remaining - $quantity;
                            $message .= " (Còn lại $con_lai cuốn chưa trả)";
                        }
                    }
                    
                    // Reload thông tin độc giả
                    if (isset($_POST['reader_id_find'])) {
                        $reader_id = (int)$_POST['reader_id_find'];
                        $stmt_reader = $conn->prepare("SELECT id, ten, sodienthoai FROM docgia WHERE id = ?");
                        $stmt_reader->bind_param("i", $reader_id);
                        $stmt_reader->execute();
                        $reader_info = $stmt_reader->get_result()->fetch_assoc();
                        $stmt_reader->close();
                        
                        if ($reader_info) {
                            $stmt_borrow = $conn->prepare("CALL thudsmuonchocdg(?)");
                            $stmt_borrow->bind_param("i", $reader_id);
                            $stmt_borrow->execute();
                            $borrow_list = $stmt_borrow->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt_borrow->close();
                        }
                    }
                    
                } catch (mysqli_sql_exception $e) {
                    $error_msg = $e->getMessage();
                    
                    if (strpos($error_msg, 'Số lượng trả') !== false) {
                        $message = "❌ Số lượng trả không hợp lệ!";
                    } elseif (strpos($error_msg, 'Không tìm thấy') !== false) {
                        $message = "❌ Không tìm thấy phiếu mượn!";
                    } else {
                        $message = "❌ Lỗi: " . $error_msg;
                    }
                    
                    $conn->query("SET @ALLOW_TRASACH_INSERT = NULL");
                }
            }
        } else {
            $message = "❌ Không tìm thấy phiếu mượn!";
        }
    }
}
    }
  }
// ========== Xử lý tìm kiếm ==========
$search_keyword = $_POST['search_keyword'] ?? '';
$search_category = $_POST['search_category'] ?? '';

if (isset($_POST['search_books'])) $active_form = 'list-books';
if (isset($_POST['search_readers'])) $active_form = 'list-readers';
if (isset($_POST['search_borrows'])) $active_form = 'borrow-book';
?>

<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>📚 Quản lý Thư Viện</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="sidebar">
    <h1>📚 Thư Viện</h1>
    <ul class="sidebar-menu">
      <li><button onclick="showForm('list-books')">Quản Lý Sách</button></li>
      <li><button onclick="showForm('list-readers')">Quản Lý Độc Giả</button></li>
      <li><button onclick="showForm('borrow-book')">Mượn Sách</button></li>
      <li><button onclick="showForm('return-book')">Trả Sách</button></li>
    </ul>
  </div>

  <div class="main-content">
    <?php if(!empty($message)): ?>
      <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- DANH SÁCH SÁCH -->
    <div data-id="list-books" class="form-section active">
      <h2> Quản Lý Sách</h2>
      
      <!-- Tab Navigation -->
      <div style="margin-bottom: 20px;">
        <button onclick="showBookTab('list')" id="tab-list" class="tab-btn active">Danh Sách</button>
        <button onclick="showBookTab('add')" id="tab-add" class="tab-btn">Thêm Sách</button>
        <button onclick="showBookTab('edit')" id="tab-edit" class="tab-btn">Cập Nhật Sách</button>
      </div>

      <!-- Tab Danh Sách -->
      <div id="book-list" class="book-tab active">
        <form method="post" class="search-box">
          <input type="number" name="search_id_books" placeholder="Tìm theo ID sách..." value="<?= htmlspecialchars($search_id_books ?? '') ?>" min="1">
          <button name="search_books">🔍 Tìm kiếm</button>
        </form>
        
      <!-- Xử lý tìm kiếm sách -->
        <?php
        $sql = "SELECT * FROM sach WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($_POST['search_id_books'])) {
         $search_id_books = (int)$_POST['search_id_books'];
         $sql .= " AND id = ?";
         $params[] = $search_id_books;
         $types .= "i";
        }
        
        $sql .= " ORDER BY id";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $books = $stmt->get_result();
        if (isset($_POST['search_books']) && $books->num_rows === 0) {
    echo "<p style='color:red; text-align:center;'>ID sách không tồn tại.</p>";
}
        ?>

        <table>
          <tr>
            <th>ID</th>
            <th>Tên Sách</th>
            <th>Tác Giả</th>
            <th>Năm XB</th>
            <th>Giá Bìa</th>
            <th>Số Lượng</th>
            <th>Thao Tác</th>
          </tr>
          <?php while($book = $books->fetch_assoc()): ?>
            <tr>
              <td><?= $book['id'] ?></td>
              <td><?= htmlspecialchars($book['ten']) ?></td>
              <td><?= htmlspecialchars($book['tacgia']) ?></td>
              <td><?= $book['namxuatban'] ?></td>
              <td><?= number_format($book['giabia']) ?> đ</td>
              <td><?= $book['soluong'] ?></td>
              <td>
                <button class="btn-action btn-edit" onclick="editBook(<?= $book['id'] ?>, '<?= addslashes($book['ten']) ?>', '<?= addslashes($book['tacgia']) ?>', <?= $book['namxuatban'] ?>, <?= $book['giabia'] ?>, <?= $book['soluong'] ?>)">Cập Nhật</button>
                <a href="?delete_book=<?= $book['id'] ?>" onclick="return confirm('Xác nhận xóa sách này?')" class="btn-action btn-delete" style="text-decoration: none;">Xóa</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      </div>

      <!-- Tab Thêm Sách -->
      <div id="book-add" class="book-tab">
        <form id="addBookForm" method="post" novalidate>
          <div class="grid">
            <input type="number" id="book_id" name="book_id" placeholder="ID sách" required>
            <input id="book_name" name="book_name" placeholder="Tên sách" required>
            <input id="author" name="author" placeholder="Tác giả" required>
            <input type="number" id="publish_year" name="publish_year" placeholder="Năm xuất bản" required>
            <input type="number" id="price" name="price" placeholder="Giá bìa ((10000-1000000VNĐ VNĐ)" required>
            <input type="number" id="quantity" name="quantity" placeholder="Số lượng" required>          </div>
          <button type="submit" name="add_book">Thêm Sách</button>
        </form>
      </div>

      <!-- Tab Cập Nhật -->
      <div id="book-edit" class="book-tab">
        <form method="post">
          <div class="grid">
            <input type="number" 
                   id="book_id_update" 
                   name="book_id_update" 
                   placeholder="ID sách (không thể thay đổi)" 
                   min="1" 
                   value="<?= isset($update_book_data) ? htmlspecialchars($update_book_data['id']) : '' ?>"
                   readonly
                   required
                   style="background-color: #f0f0f0; cursor: not-allowed;">
                   
            <input id="book_name_update" 
                   name="book_name_update" 
                   placeholder="Tên sách mới"
                   value="<?= isset($update_book_data) ? htmlspecialchars($update_book_data['name']) : '' ?>"
                   required>
                   
            <input id="author_update" 
                   name="author_update" 
                   placeholder="Tác giả mới"
                   value="<?= isset($update_book_data) ? htmlspecialchars($update_book_data['author']) : '' ?>"
                   required>
                   
            <input type="number" 
                   id="publish_year_update" 
                   name="publish_year_update" 
                   placeholder="Năm xuất bản"
                   value="<?= isset($update_book_data) ? htmlspecialchars($update_book_data['year']) : '' ?>"
                   required>
                   
            <input type="number" 
                   id="price_update" 
                   name="price_update" 
                   placeholder="Giá bìa (10000-1000000VNĐ)"
                   value="<?= isset($update_book_data) ? htmlspecialchars($update_book_data['price']) : '' ?>"
                   required>
                   
            <input type="number" 
                   id="quantity_update" 
                   name="quantity_update" 
                   placeholder="Số lượng"
                   value="<?= isset($update_book_data) ? htmlspecialchars($update_book_data['quantity']) : '' ?>"
                   required>
          </div>
          <button name="update_book">Cập Nhật Sách</button>
        </form>
      </div>
    </div>

    <!-- DANH SÁCH ĐỘC GIẢ -->
    <div data-id="list-readers" class="form-section">
      <h2>👥 Quản Lý Độc Giả</h2>

      <!-- Tab Navigation -->
      <div style="margin-bottom: 20px;">
        <button onclick="showReaderTab('list')" id="tab-reader-list" class="tab-btn-reader active">Danh Sách</button>
        <button onclick="showReaderTab('add')" id="tab-reader-add" class="tab-btn-reader">Thêm Độc Giả</button>
        <button onclick="showReaderTab('edit')" id="tab-reader-edit" class="tab-btn-reader">Cập Nhật Độc Giả</button>
      </div>

      <!-- Tab Danh Sách -->
      <div id="reader-tab-list" class="reader-tab active">
        <form method="post" class="search-box">
          <input type="number" name="search_id_reader" placeholder="Tìm theo ID độc giả..." value="<?= htmlspecialchars($search_id_reader ?? '') ?>" min="1">
          <button name="search_readers">🔍 Tìm kiếm</button>
        </form>

        <?php
        $sql = "SELECT * FROM docgia WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($_POST['search_id_reader'])) {
            $search_id_reader = (int)$_POST['search_id_reader'];
            $sql .= " AND id = ?";
            $params[] = $search_id_reader;
            $types .= "i";
        }

        $sql .= " ORDER BY id";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $readers = $stmt->get_result();
        if (isset($_POST['search_readers']) && $readers->num_rows === 0) {
           echo "<p style='color:red; text-align:center;'>ID độc giả không tồn tại.</p>";
        }


        ?>

        <table>
          <tr>
            <th>ID</th>
            <th>Họ Tên</th>
            <th>Năm Sinh</th>
            <th>Số Điện Thoại</th>
            <th>Thao Tác</th>
          </tr>
          <?php while ($reader = $readers->fetch_assoc()): ?>
            <tr>
              <td><?= $reader['id'] ?></td>
              <td><?= htmlspecialchars($reader['ten']) ?></td>
              <td><?= $reader['namsinh'] ?></td>
              <td><?= htmlspecialchars($reader['sodienthoai']) ?></td>
              <td>
                <button class="btn-action btn-edit"
                  type="button"
                  onclick="editReader(<?= $reader['id'] ?>, '<?= addslashes($reader['ten']) ?>', <?= $reader['namsinh'] ?>, '<?= addslashes($reader['sodienthoai']) ?>')">
                  Cập Nhật
                </button>
                <a href="?delete_reader=<?= $reader['id'] ?>" 
                  onclick="return confirm('Xác nhận xóa độc giả này?')" 
                  class="btn-action btn-delete" style="text-decoration: none;">
                  Xóa
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      </div>

      <!-- Tab Thêm Độc Giả -->
      <div id="reader-tab-add" class="reader-tab">
        <form method="post">
          <div class="grid">
            <input type="number" name="reader_id" placeholder="ID độc giả" min="1" required>
            <input name="reader_name" placeholder="Họ tên độc giả" required>
            <input type="number" name="birth_year" placeholder="Năm sinh (1960–2007)" required>
            <input name="reader_phone" placeholder="Số điện thoại (10 chữ số, bắt đầu bằng 0)" required>
          </div>
          <button name="add_reader">Thêm Độc Giả</button>
        </form>
      </div>

      <!-- Tab Cập Nhật Độc Giả -->
      <div id="reader-tab-edit" class="reader-tab">
        <form method="post">
          <div class="grid">
            <input type="number" id="reader_id_update" name="reader_id_update" placeholder="ID độc giả (không thể thay đổi)" min="1" required readonly style="background-color: #f0f0f0; cursor: not-allowed;">
            <input id="reader_name_update" name="reader_name_update" placeholder="Tên độc giả mới" required>
            <input type="number" id="birth_year_update" name="birth_year_update"  placeholder="Năm sinh mới" required>
            <input id="phone_update" name="reader_phone_update" placeholder="Số điện thoại mới (10 chữ số, bắt đầu bằng 0)" required>
          </div>
          <button name="update_reader">Cập Nhật Độc Giả</button>
        </form>
      </div>
    </div>

    <!-- FORM MƯỢN SÁCH -->
    <div data-id="borrow-book" class="form-section">
      <h2>📚 Mượn Sách</h2>
      
  <!-- Form tạo phiếu mượn -->
  <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
    <h3 style="margin-bottom: 15px;">Tạo phiếu mượn mới</h3>
    <form method="post">
      <div class="grid">
        <input type="number" name="reader_id"  placeholder="Nhập ID độc giả" required>
        <input type="number" name="book_id"  placeholder="Nhập ID sách" required>
        <input type="number" name="borrow_quantity"  placeholder="Số lượng (1–5)" required>
        <input type="date" name="borrow_date" value="<?= date('Y-m-d') ?>"  required>
      </div>
          <textarea name="borrow_note" placeholder="Ghi chú (không bắt buộc)" style="width: 100%; margin-top: 10px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit;"></textarea>
      <button name="borrow_book">Tạo Phiếu Mượn</button>
    </form>
  </div>

  <!-- Danh sách phiếu mượn -->
  <h3>📋 Danh Sách Phiếu Mượn</h3>
  <?php
  $sql = " SELECT
  ms.id AS id,
  ms.madocgia,
  dg.ten AS tendocgia,
  ms.masach,
  s.ten AS tensach,
  ms.soluongmuon,
  IFNULL(rt.total_returned, 0) AS total_returned,
  (ms.soluongmuon - IFNULL(rt.total_returned,0)) AS conlai,
  ms.ngaymuon,
  ms.hantra,
  ms.ngaytrathucte,
  ms.trangthai,
  ms.phiphat,
  ms.ghichu
FROM muonsach ms
LEFT JOIN (
  SELECT mamuon, SUM(soluongtra) AS total_returned
  FROM trasach
  GROUP BY mamuon
) rt ON rt.mamuon = ms.id
JOIN docgia dg ON dg.id = ms.madocgia
JOIN sach s ON s.id = ms.masach
WHERE 1=1
";
$params = [];
$types = "";
if (!empty($search_keyword) && isset($_POST['search_borrows'])) {
    $sql .= " AND ms.madocgia = ?";
    $params[] = (int)$search_keyword;
    $types .= "i";
}
$sql .= " ORDER BY ms.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$borrows = $stmt->get_result();
  ?>
  <table>
    <tr>
      <th>ID Phiếu</th>
      <th>Độc Giả</th>
      <th>Sách</th>
      <th>SL Mượn</th>
      <th>SL Trả</th>
      <th>Còn Lại</th>
      <th>Ngày Mượn</th>
      <th>Hạn Trả</th>
      <th>Ngày Trả</th>
      <th>Trạng Thái</th>
      <th>Phí Phạt</th>
      <th>Ghi Chú</th>
    </tr>
    <?php while($borrow = $borrows->fetch_assoc()): ?>
      <tr>
        <td><?= $borrow['id'] ?></td>
        <td><?= htmlspecialchars($borrow['tendocgia']) ?></td>
        <td><?= htmlspecialchars($borrow['tensach']) ?></td>
        <td><?= $borrow['soluongmuon'] ?></td>
        <td><?= isset($borrow['total_returned']) ? $borrow['total_returned'] : 0 ?></td>
        <td><?= isset($borrow['conlai']) ? $borrow['conlai'] : ($borrow['soluongmuon'] - (isset($borrow['total_returned']) ? $borrow['total_returned'] : 0)) ?></td>
        <td><?= date('d/m/Y', strtotime($borrow['ngaymuon'])) ?></td>
        <td><?= date('d/m/Y', strtotime($borrow['hantra'])) ?></td>
        <td><?= $borrow['ngaytrathucte'] ? date('d/m/Y', strtotime($borrow['ngaytrathucte'])) : '-' ?></td>
        <td><?= htmlspecialchars($borrow['trangthai']) ?></td>
        <td><?= number_format($borrow['phiphat']) ?> đ</td>
        <td><?= htmlspecialchars($borrow['ghichu']) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>
    <!-- FORM TRẢ SÁCH -->
    <div data-id="return-book" class="form-section">
      <h2>🔄 Trả Sách</h2>
      
 <!-- Form tìm độc giả -->
  <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
    <h3 style="margin-bottom: 15px;">Tìm độc giả để trả sách</h3>
    <form method="post">
      <input type="number" name="reader_id_find" placeholder="Nhập ID độc giả" required>
      <button name="find_reader" style="margin-top: 10px;">Tìm độc giả</button>
    </form>
  </div>

  <?php if(isset($_POST['find_reader']) || isset($_POST['return_book_submit'])): ?>
    <?php if($reader_info): ?>
      <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-bottom: 15px; color: #5b21b6;">👤 Thông tin độc giả</h3>
        <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
          <div>
            <strong>ID Độc giả:</strong><br>
            <span><?= $reader_info['id'] ?></span>
          </div>
          <div>
            <strong>Họ và tên:</strong><br>
            <span><?= htmlspecialchars($reader_info['ten']) ?></span>
          </div>
          <div>
            <strong>Số điện thoại:</strong><br>
            <span><?= htmlspecialchars($reader_info['sodienthoai']) ?></span>
          </div>
        </div>
      </div>

      <?php if(!empty($borrow_list)): ?>
        <table>
          <tr>
            <th>ID Phiếu</th>
            <th>ID Sách</th>
            <th>Tên sách</th>
            <th>Ngày mượn</th>
            <th>Hạn trả</th>
            <th>SL mượn</th>
            <th>Tình trạng</th>
            <th>Trả sách</th>
          </tr>
          <?php 
          $today = new DateTime();
          foreach($borrow_list as $borrow): 
            $hantra = new DateTime($borrow['hantra']);
            $diff = $today->diff($hantra);
            $days_late = $today > $hantra ? $today->diff($hantra)->days : 0;
            $status = $today > $hantra ? "Trễ $days_late ngày" : "Còn " . $diff->days . " ngày";
          ?>
          <tr>
            <td><?= $borrow['mamuon'] ?></td>
            <td><?= $borrow['masach'] ?></td>
            <td><?= htmlspecialchars($borrow['tensach']) ?></td>
            <td><?= date('d/m/Y', strtotime($borrow['ngaymuon'])) ?></td>
            <td><?= date('d/m/Y', strtotime($borrow['hantra'])) ?></td>
            <td><?= $borrow['conlai'] ?></td>
            <td style="color: <?= $today > $hantra ? '#dc2626' : '#16a34a' ?>">
              <?= $status ?>
            </td>
            <td>
              <form method="post" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="reader_id_find" value="<?= $reader_info['id'] ?>">
                <input type="hidden" name="find_reader" value="1">
                <input type="hidden" name="borrow_id" value="<?= $borrow['mamuon'] ?>">
                <input type="number" name="quantity_return" 
                      min="0" 
                      value="<?= $borrow['conlai'] ?>" 
                      required
                      style="width: 60px; padding: 8px;"
                      title="Nhập 0 nếu mất sách">
                <input type="date" 
                       name="return_date" 
                       value="<?= date('Y-m-d') ?>" 
                       required 
                       style="width: 120px; padding: 8px;">
                <input type="text" 
                       name="return_note" 
                       placeholder="Ghi chú..." 
                       style="width: 150px; padding: 8px;">
                <button name="return_book_submit" 
                        style="width: auto; padding: 8px 16px;">
                    Trả
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="text-align: center; margin-top: 20px; color: #16a34a;">
           Độc giả không có sách nào đang mượn
        </p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

  <p style="font-size: 13px; color: #64748b; margin: 20px 0;">
    * Trả đúng hạn: 0đ | Trả trễ: 2,000đ/ngày/cuốn | Mất sách: phạt = giá bìa
  </p>

  <!-- Danh sách phiếu trả -->
  <hr style="margin: 40px 0; border: none; border-top: 2px solid #e2e8f0;">
  <h3>✅ Danh Sách Phiếu Trả</h3>
  <?php
 $sql = "
SELECT 
  ts.id AS id,
  ts.madocgia,
  dg.ten AS tendocgia,
  ts.masach,
  s.ten AS tensach,
  ts.soluongtra,
  ms.soluongmuon,
  ts.ngaytrathucte,
  ts.trangthai,
  ts.phiphat,
  ts.ghichu
FROM trasach ts
JOIN muonsach ms ON ts.mamuon = ms.id
JOIN docgia dg ON ts.madocgia = dg.id
JOIN sach s ON ts.masach = s.id
WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search_keyword) && isset($_POST['search_returns'])) {
    $sql .= " AND ts.madocgia = ?";
    $params[] = (int)$search_keyword;
    $types .= "i";
}

$sql .= " ORDER BY ts.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$returns = $stmt->get_result();
if (isset($_POST['search_returns']) && $returns->num_rows === 0) {
    echo "<p style='color:red; text-align:center;'>ID phiếu trả không tồn tại.</p>";
}

  ?>
 <table>
  <tr>
    <th>ID Trả</th>
    <th>Độc Giả</th>
    <th>Sách</th>
    <th>SL Trả</th>
    <th>SL Mượn</th>
    <th>Ngày Trả</th>
    <th>Trạng Thái</th>
    <th>Phí Phạt</th>
    <th>Ghi Chú</th>
  </tr>
  <?php while($return = $returns->fetch_assoc()): ?>
    <tr>
      <td><?= $return['id'] ?></td>
      <td><?= htmlspecialchars($return['tendocgia']) ?></td>
      <td><?= htmlspecialchars($return['tensach']) ?></td>
      <td><?= $return['soluongtra'] ?? 0 ?></td>
      <td><?= $return['soluongmuon'] ?? '-' ?></td>
      <td><?= date('d/m/Y', strtotime($return['ngaytrathucte'])) ?></td>
      <td><?= htmlspecialchars($return['trangthai']) ?></td>
      <td><?= number_format($return['phiphat']) ?> đ</td>
      <td><?= htmlspecialchars($return['ghichu']) ?></td>
    </tr>
  <?php endwhile; ?>
</table>
</div>

<script>
// ========== FUNCTIONS ==========
function showForm(formId) {
    // Ẩn tất cả form sections
    document.querySelectorAll('.form-section').forEach(form => {
        form.classList.remove('active');
    });
    
    // Hiện form được chọn
    const targetForm = document.querySelector(`.form-section[data-id="${formId}"]`);
    if (targetForm) {
        targetForm.classList.add('active');
    } else {
        console.error('Không tìm thấy form với data-id:', formId);
    }
    
    // Cập nhật active button
    document.querySelectorAll('.sidebar-menu button').forEach(btn => {
        btn.classList.remove('active');
    });
    const targetBtn = document.querySelector(`button[onclick="showForm('${formId}')"]`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }
}

function showBookTab(tabName) {
    document.querySelectorAll('.book-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const targetTab = document.getElementById('book-' + tabName);
    const targetBtn = document.getElementById('tab-' + tabName);
    
    if (targetTab) targetTab.classList.add('active');
    if (targetBtn) targetBtn.classList.add('active');
}

function editBook(id, name, author, year, price, quantity) {
    showBookTab('edit');
    document.getElementById('book_id_update').value = id;
    document.getElementById('book_name_update').value = name;
    document.getElementById('author_update').value = author;
    document.getElementById('publish_year_update').value = year;
    document.getElementById('price_update').value = price;
    document.getElementById('quantity_update').value = quantity;
}

function showReaderTab(tabName) {
    document.querySelectorAll('.reader-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-btn-reader').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const targetTab = document.getElementById('reader-tab-' + tabName);
    const targetBtn = document.getElementById('tab-reader-' + tabName);
    
    if (targetTab) targetTab.classList.add('active');
    if (targetBtn) targetBtn.classList.add('active');
}

function editReader(id, name, birthYear, phone) {
    showReaderTab('edit');
    document.getElementById('reader_id_update').value = id;
    document.getElementById('reader_name_update').value = name;
    document.getElementById('birth_year_update').value = birthYear;
    document.getElementById('phone_update').value = phone;
}

// ========== DOM READY ==========
document.addEventListener('DOMContentLoaded', function() {
    // 1. Hiển thị form đúng khi load trang
    const activeForm = <?= json_encode($active_form ?? 'list-books') ?>;
    showForm(activeForm);

    // 2. Nếu có lỗi validation cập nhật sách, mở tab edit
    <?php if (isset($update_book_data)): ?>
    if (typeof showBookTab === 'function') {
        showBookTab('edit');
    }
    <?php endif; ?>

    // 3. Validation form thêm sách
    const addBookForm = document.getElementById('addBookForm');
    if (addBookForm) {
        addBookForm.addEventListener('submit', function(e) {
            let errors = [];
            
            const idEl = document.getElementById('book_id');
            const nameEl = document.getElementById('book_name');
            const authorEl = document.getElementById('author');
            const yearEl = document.getElementById('publish_year');
            const priceEl = document.getElementById('price');
            const qtyEl = document.getElementById('quantity');

            const id = idEl.value.trim();
            const name = nameEl.value.trim();
            const author = authorEl.value.trim();
            const year = parseInt(yearEl.value, 10);
            const price = parseInt(priceEl.value, 10);
            const quantity = parseInt(qtyEl.value, 10);

            const nameRegex = /^[A-Za-zÀ-ỹ0-9 ]+$/;
            const authorRegex = /^[A-Za-zÀ-ỹ ]+$/;

            // Reset style lỗi cũ
            [idEl, nameEl, authorEl, yearEl, priceEl, qtyEl].forEach(i => {
                i.style.borderColor = '';
                i.dataset.error = '';
            });
        });
    }
}); 
</script>
</body>
</html>