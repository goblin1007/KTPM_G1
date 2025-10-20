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

// ========== Xử lý thêm sách ==========
if (isset($_POST['add_book'])) {
    $active_form = 'add-book';
    $id = (int)$_POST['book_id'];
    $name = trim($_POST['book_name']);
    $author = trim($_POST['author']);
    $year = (int)$_POST['publish_year'];
    $price = (int)$_POST['price'];
    $quantity = (int)$_POST['quantity'];

    if ($id < 1) $message = "❌ ID sách phải là số nguyên dương!";
    elseif (empty($name)) $message = "❌ Tên sách không được để trống!";
    elseif (!valid_book_name($name)) $message = "❌ Tên sách chỉ chứa chữ, số và khoảng trắng!";
    elseif (!only_letters_spaces($author)) $message = "❌ Tên tác giả chỉ chứa chữ!";
    elseif ($year < 1900 || $year > 2025) $message = "❌ Năm xuất bản phải từ 1900-2025!";
    elseif ($price < 10000 || $price > 1000000) $message = "❌ Giá bìa phải từ 10,000-1,000,000 VNĐ!";
    elseif ($quantity < 1) $message = "❌ Số lượng phải >= 1!";
    else {
        try {
            $stmt = $conn->prepare("CALL thucthemsach(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiii", $id, $name, $author, $year, $price, $quantity);
            $stmt->execute();
            $message = "✅ Thêm sách thành công!";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
        }
    }
}

// ========== Xử lý thêm độc giả ==========
if (isset($_POST['add_reader'])) {
    $active_form = 'add-reader';
    $id = (int)$_POST['reader_id'];
    $name = trim($_POST['reader_name']);
    $birth_year = (int)$_POST['birth_year'];
$phone = isset($_POST['reader_phone']) ? trim($_POST['reader_phone']) : '';

    if ($id < 1) $message = "❌ ID độc giả phải là số nguyên dương!";
    elseif (empty($name)) $message = "❌ Tên độc giả không được để trống!";
    elseif (!only_letters_spaces($name)) $message = "❌ Tên độc giả chỉ chứa chữ!";
    elseif ($birth_year < 1960 || $birth_year > 2007) $message = "❌ Năm sinh phải từ 1960-2007!";
    elseif (!valid_phone($phone)) $message = "❌ Số điện thoại phải đúng 10 chữ số và bắt đầu bằng 0!";
    else {
        try {
            $stmt = $conn->prepare("CALL thucthemdocgia(?, ?, ?, ?)");
            $stmt->bind_param("isis", $id, $name, $birth_year, $phone);
            $stmt->execute();
            $message = "✅ Thêm độc giả thành công!";
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

    if ($reader_id < 1) $message = "❌ ID độc giả không hợp lệ!";
    elseif ($book_id < 1) $message = "❌ ID sách không hợp lệ!";
    elseif ($quantity < 1 || $quantity > 5) $message = "❌ Số lượng mượn phải từ 1-5!";
    elseif (empty($borrow_date)) $message = "❌ Ngày mượn không được để trống!";
    else {
        try {
            // ✅ Gọi đúng 5 tham số
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

// ========== Xử lý cập nhật sách ==========
if (isset($_POST['update_book'])) {
    $active_form = 'update-book';
    $id = (int)$_POST['book_id_update'];
    $name = trim($_POST['book_name_update']);
    $author = trim($_POST['author_update']);
    $year = (int)$_POST['publish_year_update'];
    $price = (int)$_POST['price_update'];
    $quantity = (int)$_POST['quantity_update'];

    if ($id < 1) $message = "❌ ID sách không hợp lệ!";
    elseif (empty($name)) $message = "❌ Tên sách không được để trống!";
    elseif (!valid_book_name($name)) $message = "❌ Tên sách chỉ chứa chữ, số và khoảng trắng!";
    elseif (!only_letters_spaces($author)) $message = "❌ Tên tác giả chỉ chứa chữ!";
    elseif ($year < 1900 || $year > 2025) $message = "❌ Năm xuất bản phải từ 1900-2025!";
    elseif ($price < 10000 || $price > 1000000) $message = "❌ Giá bìa phải từ 10,000-1,000,000 VNĐ!";
    elseif ($quantity < 0) $message = "❌ Số lượng không hợp lệ!";
    else {
        try {
            $stmt = $conn->prepare("CALL thucapnhatsach(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiii", $id, $name, $author, $year, $price, $quantity);
            $stmt->execute();
            $message = "✅ Cập nhật sách thành công!";
            $stmt->close();
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
            $message = "❌ Không tìm thấy độc giả!";
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
    
    if ($row = $result->fetch_assoc()) {
        $remaining = $row['soluongmuon'] - $row['soluongtra'];
        if ($quantity <= 0 || $quantity > $remaining) {
            $message = "❌ Số lượng trả không hợp lệ!";
        } else {
            try {
                // Set flag for trasach insert
                $conn->query("SET @ALLOW_TRASACH_INSERT = 1");
                
                // Call stored procedure
                $stmt = $conn->prepare("CALL thuctrasach(?, ?, ?, NULL)");
                $stmt->bind_param("iis", $borrow_id, $quantity, $return_date);
                $stmt->execute();
                
                // Reset flag
                $conn->query("SET @ALLOW_TRASACH_INSERT = NULL");
                
                $message = "✅ Đã trả " . $quantity . " cuốn " . $row['tensach'];
                
            } catch (mysqli_sql_exception $e) {
                $message = "❌ Lỗi: " . $e->getMessage();
                $conn->query("SET @ALLOW_TRASACH_INSERT = NULL");
            }
        }
    } else {
        $message = "❌ Không tìm thấy phiếu mượn!";
    }
    $stmt->close();
}

// ========== Xử lý tìm kiếm ==========
$search_keyword = $_POST['search_keyword'] ?? '';
$search_category = $_POST['search_category'] ?? '';

if (isset($_POST['search_books'])) $active_form = 'list-books';
if (isset($_POST['search_readers'])) $active_form = 'list-readers';
if (isset($_POST['search_borrows'])) $active_form = 'list-borrows';
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
    <div data-id="list-books" class="form-section">
      <h2>📖 Quản Lý Sách</h2>
      
      <!-- Tab Navigation -->
      <div style="margin-bottom: 20px;">
        <button onclick="showBookTab('list')" id="tab-list" class="tab-btn active">Danh Sách</button>
        <button onclick="showBookTab('add')" id="tab-add" class="tab-btn">Thêm Sách</button>
        <button onclick="showBookTab('edit')" id="tab-edit" class="tab-btn">Cập Nhật Sách</button>
      </div>

      <!-- Tab Danh Sách -->
      <div id="book-list" class="book-tab active">
        <form method="post" class="search-box">
          <input name="search_keyword" placeholder="Tìm theo tên sách..." value="<?= htmlspecialchars($search_keyword) ?>">
          <input name="search_category" placeholder="Tìm theo tác giả..." value="<?= htmlspecialchars($search_category) ?>">
          <button name="search_books">🔍 Tìm kiếm</button>
        </form>

        <?php
        $sql = "SELECT * FROM sach WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($search_keyword)) {
            $sql .= " AND ten LIKE ?";
            $params[] = "%$search_keyword%";
            $types .= "s";
        }
        if (!empty($search_category)) {
            $sql .= " AND tacgia LIKE ?";
            $params[] = "%$search_category%";
            $types .= "s";
        }
        
        $sql .= " ORDER BY id";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $books = $stmt->get_result();
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
        <form method="post">
          <div class="grid">
            <input type="number" name="book_id" placeholder="ID sách" min="1" required>
            <input name="book_name" placeholder="Tên sách" required>
            <input name="author" placeholder="Tác giả" required>
            <input type="number" name="publish_year" min="1900" max="2025" placeholder="Năm xuất bản" required>
            <input type="number" name="price" min="10000" max="1000000" placeholder="Giá bìa (VNĐ)" required>
            <input type="number" name="quantity" min="1" placeholder="Số lượng" required>
          </div>
          <textarea name="description" placeholder="Ghi chú (Tên: chỉ chữ, số và khoảng trắng | Tác giả: chỉ chữ)"></textarea>
          <button name="add_book">Thêm Sách</button>
        </form>
      </div>

      <!-- Tab Cập Nhật -->
      <div id="book-edit" class="book-tab">
        <form method="post">
          <div class="grid">
            <input type="number" id="book_id_update" name="book_id_update" placeholder="ID sách cần cập nhật" min="1" required>
            <input id="book_name_update" name="book_name_update" placeholder="Tên sách mới" required>
            <input id="author_update" name="author_update" placeholder="Tác giả mới" required>
            <input type="number" id="publish_year_update" name="publish_year_update" min="1900" max="2025" placeholder="Năm xuất bản" required>
            <input type="number" id="price_update" name="price_update" min="10000" max="1000000" placeholder="Giá bìa (VNĐ)" required>
            <input type="number" id="quantity_update" name="quantity_update" min="0" placeholder="Số lượng" required>
          </div>
          <button name="update_book">Cập Nhật Sách</button>
        </form>
      </div>
    </div>

<!-- DANH SÁCH ĐỘC GIẢ -->
<div data-id="list-readers" class="form-section">
  <h2> Quản Lý Độc Giả</h2>

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
    <input name="search_phone_reader" placeholder="Tìm theo số điện thoại..." value="<?= htmlspecialchars($search_phone_reader ?? '') ?>">
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
  if (!empty($_POST['search_phone_reader'])) {
      $search_phone_reader = trim($_POST['search_phone_reader']);
      $sql .= " AND sodienthoai LIKE ?";
      $params[] = "%$search_phone_reader%";
      $types .= "s";
  }

    $sql .= " ORDER BY id";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $readers = $stmt->get_result();
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
        <input type="number" name="birth_year" placeholder="Năm sinh (1960–2007)" min="1960" max="2007" required>
        <input name="reader_phone" placeholder="Số điện thoại (10 chữ số, bắt đầu bằng 0)" pattern="0[0-9]{9}" required>
      </div>
      <textarea name="description_reader" placeholder="Ghi chú (Tên chỉ chữ | Năm sinh từ 1960–2007 | SĐT hợp lệ)"></textarea>
      <button name="add_reader">Thêm Độc Giả</button>
    </form>
  </div>

  <!-- Tab Cập Nhật Độc Giả -->
  <div id="reader-tab-edit" class="reader-tab">
    <form method="post">
      <div class="grid">
        <input type="number" id="reader_id_update" name="reader_id_update" placeholder="ID độc giả cần cập nhật" min="1" required>
        <input id="reader_name_update" name="reader_name_update" placeholder="Tên độc giả mới" required>
        <input type="number" id="birth_year_update" name="birth_year_update" min="1960" max="2007" placeholder="Năm sinh mới" required>
        <input id="phone_update" name="reader_phone_update" placeholder="Số điện thoại mới (10 chữ số, bắt đầu bằng 0)" pattern="0[0-9]{9}" required>
      </div>
      <button name="update_reader">Cập Nhật Độc Giả</button>
    </form>
  </div>
</div>

<script>
function showReaderTab(tabName) {
  // Ẩn tất cả tab độc giả
  document.querySelectorAll('.reader-tab').forEach(tab => tab.classList.remove('active'));
  // Bỏ active của tất cả nút độc giả
  document.querySelectorAll('.tab-btn-reader').forEach(btn => btn.classList.remove('active'));
  // Hiển thị tab được chọn
  document.getElementById('reader-tab-' + tabName).classList.add('active');
  document.getElementById('tab-reader-' + tabName).classList.add('active');
}

function editReader(id, name, birthYear, phone) {
  showReaderTab('edit');
  document.getElementById('reader_id_update').value = id;
  document.getElementById('reader_name_update').value = name;
  document.getElementById('birth_year_update').value = birthYear;
  document.getElementById('phone_update').value = phone;
}
</script>

    <!-- FORM THÊM ĐỘC GIẢ -->
    <form method="post" data-id="add-reader" class="form-section">
      <h2>➕ Thêm Độc Giả</h2>
      <div class="grid">
        <input type="number" name="reader_id" placeholder="ID độc giả" min="1" required>
        <input name="reader_name" placeholder="Họ và tên" required>
        <input type="number" name="birth_year" min="1960" max="2007" placeholder="Năm sinh (1960-2007)" required>
        <input name="reader_phone" placeholder="Số điện thoại (0912345678)" required>
      </div>
      <button name="add_reader">Thêm Độc Giả</button>
    </form>

<!-- FORM MƯỢN SÁCH -->
<div data-id="borrow-book" class="form-section">
  <h2>📚 Mượn Sách</h2>
  
  <!-- Form tạo phiếu mượn -->
  <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
    <h3 style="margin-bottom: 15px;">Tạo phiếu mượn mới</h3>
    <form method="post">
      <div class="grid">
        <input type="number" name="reader_id" min="1" placeholder="Nhập ID độc giả" required>
        <input type="number" name="book_id" min="1" placeholder="Nhập ID sách" required>
        <input type="number" name="borrow_quantity" min="1" max="5" placeholder="Số lượng (1–5)" required>
        <input type="date" name="borrow_date" value="<?= date('Y-m-d') ?>" min="2025-01-01" required>
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
      <th>Ngày Mượn</th>
      <th>Hạn Trả</th>
      <th>Ngày Trả</th>
      <th>Trạng Thái</th>
     
    </tr>
    <?php while($borrow = $borrows->fetch_assoc()): ?>
      <tr>
        <td><?= $borrow['id'] ?></td>
        <td><?= htmlspecialchars($borrow['tendocgia']) ?></td>
        <td><?= htmlspecialchars($borrow['tensach']) ?></td>
        <td><?= $borrow['soluongmuon'] ?></td>
        <td><?= isset($borrow['total_returned']) ? $borrow['total_returned'] : 0 ?></td>
        <td><?= date('d/m/Y', strtotime($borrow['ngaymuon'])) ?></td>
        <td><?= date('d/m/Y', strtotime($borrow['hantra'])) ?></td>
        <td><?= $borrow['ngaytrathucte'] ? date('d/m/Y', strtotime($borrow['ngaytrathucte'])) : '-' ?></td>
        <td><?= htmlspecialchars($borrow['trangthai']) ?></td>
        
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
                <input type="hidden" name="reader_id_current" value="<?= $reader_info['id'] ?>">
                <input type="hidden" name="borrow_id" value="<?= $borrow['mamuon'] ?>">
                <input type="number" name="quantity_return" 
                       min="1" 
                       max="<?= $borrow['conlai'] ?>" 
                       value="<?= $borrow['conlai'] ?>" 
                       required
                       style="width: 60px; padding: 8px;">
                <input type="date" 
                       name="return_date" 
                       value="<?= date('Y-m-d') ?>" 
                       min="2025-01-01"
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
          ✅ Độc giả không có sách nào đang mượn
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
function showForm(formId) {
    document.querySelectorAll('.form-section').forEach(form => {
        form.classList.remove('active');
    });
    document.querySelector(`.form-section[data-id="${formId}"]`).classList.add('active');
    
    document.querySelectorAll('.sidebar-menu button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`button[onclick="showForm('${formId}')"]`).classList.add('active');
}
function showBookTab(tabName) {
  document.querySelectorAll('.book-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  document.getElementById('book-' + tabName).classList.add('active');
  document.getElementById('tab-' + tabName).classList.add('active');
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
document.addEventListener('DOMContentLoaded', function() {
    showForm('<?php echo $active_form; ?>');
});
  </script>
</body>
</html>