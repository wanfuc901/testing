<?php
require_once __DIR__ . '/../../config/config.php';
session_start();

// Chặn truy cập nếu không phải admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('forbidden');
}

$act = $_POST['action'] ?? '';

/* ================== THÊM HOẶC CẬP NHẬT PHIM ================== */
if ($act === 'create' || $act === 'update') {
    $title    = trim($_POST['title'] ?? '');
    $desc     = $_POST['description'] ?? null;
    $duration = (int)($_POST['duration'] ?? 0);
    $genreId  = (int)($_POST['genre_id'] ?? 0);
    $start    = $_POST['start_date'] ?: null;
    $end      = $_POST['end_date'] ?: null;
    $status   = $_POST['status'] ?? 'upcoming';
    $release  = $_POST['release_date'] ?: null;
    $poster   = trim($_POST['poster_url'] ?? '');
    $price    = (float)($_POST['ticket_price'] ?? 80000);

    // ✅ Upload ảnh nếu có file
    $uploadedPath = '';
    if (!empty($_FILES['poster_file']['name'])) {
        // === Lưu vào thư mục app/views/movies/ thay vì banners ===
        $uploadDir = __DIR__ . '/../../views/movies/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $fileName = basename($_FILES['poster_file']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            $newName = uniqid('movie_') . '.' . $ext;
            $target = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $target)) {
                $poster = $newName;
                // ✅ Đường dẫn public (hiển thị được trên web)
                $uploadedPath = "app/views/movies/" . $newName;
            }
        }
    }

    if ($title === '') {
        echo "<script>alert('Thiếu tiêu đề phim');history.back();</script>";
        exit;
    }

    if ($act === 'create') {
        $stmt = $conn->prepare("
            INSERT INTO movies (title, description, duration, genre_id, start_date, end_date, status, release_date, poster_url, ticket_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissssssd",
            $title, $desc, $duration, $genreId,
            $start, $end, $status, $release, $poster, $price
        );
    } else {
        $id = (int)$_POST['movie_id'];
        $stmt = $conn->prepare("
            UPDATE movies
            SET title=?, description=?, duration=?, genre_id=?, start_date=?, end_date=?, status=?, release_date=?, poster_url=?, ticket_price=?
            WHERE movie_id=?
        ");
        $stmt->bind_param("ssissssssdi",
            $title, $desc, $duration, $genreId,
            $start, $end, $status, $release, $poster, $price, $id
        );
    }

    $stmt->execute();

    // ✅ Thông báo sau khi lưu
    echo "<script>";
    echo "alert('Đã lưu thành công";
    if ($uploadedPath) echo "\\nẢnh được lưu tại: $uploadedPath";
    echo "'); history.back();";
    echo "</script>";
    exit;
}

/* ================== XÓA PHIM ================== */
if ($act === 'delete') {
    $id = (int)($_POST['movie_id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM movies WHERE movie_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>alert('Đã xóa phim');history.back();</script>";
        exit;
    }
}

/* ================== TRƯỜNG HỢP KHÔNG HỢP LỆ ================== */
http_response_code(400);
echo 'bad request';
