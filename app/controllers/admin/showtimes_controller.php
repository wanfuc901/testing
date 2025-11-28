<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/config.php';

$action = $_POST['action'] ?? '';

function calc_end_time($conn, $movie_id, $start_time) {
    $q = $conn->prepare("SELECT duration FROM movies WHERE movie_id=?");
    $q->bind_param("i", $movie_id);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();
    $duration = intval($d['duration'] ?? 0);
    if ($duration <= 0) $duration = 90; // fallback mặc định 90 phút
    return date('Y-m-d H:i:s', strtotime($start_time) + $duration * 60);
}

function check_overlap($conn, $room_id, $start, $end, $exclude_id = null) {
    $sql = "SELECT COUNT(*) AS c FROM showtimes 
            WHERE room_id=? 
              AND ((start_time BETWEEN ? AND ?) OR (end_time BETWEEN ? AND ?) 
                   OR (? BETWEEN start_time AND end_time))";
    if ($exclude_id) $sql .= " AND showtime_id != ?";
    $q = $conn->prepare($sql);
    if ($exclude_id)
        $q->bind_param("isssssi", $room_id, $start, $end, $start, $end, $start, $exclude_id);
    else
        $q->bind_param("isssss", $room_id, $start, $end, $start, $end, $start);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    return $res['c'] > 0;
}

/* ========== CREATE ========== */
if ($action === 'create') {
    $movie_id = intval($_POST['movie_id']);
    $room_id = intval($_POST['room_id']);
    $start_time = $_POST['start_time'];
    $end_time = calc_end_time($conn, $movie_id, $start_time);

    // ===== kiểm tra giờ mở cửa và thời điểm đã qua =====
    $hour = intval(date('H', strtotime($start_time))); // dùng H thay cho G
    $today = date('Y-m-d');
    $now = time();

    if ($hour < 8) {
        echo "<script>alert('Không thể thêm phim trước 8:00 sáng (giờ mở cửa)!');history.back();</script>";
        exit;
    }
    if (strtotime($start_time) < $now && date('Y-m-d', strtotime($start_time)) == $today) {
        echo "<script>alert('Không thể thêm phim vào giờ đã trôi qua!');history.back();</script>";
        exit;
    }

    // ===== kiểm tra trùng giờ =====
    if (check_overlap($conn, $room_id, $start_time, $end_time)) {
        echo "<script>alert('Trùng giờ chiếu với suất khác trong phòng này!');history.back();</script>";
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO showtimes (movie_id, room_id, start_time, end_time) VALUES (?,?,?,?)");
    $stmt->bind_param("iiss", $movie_id, $room_id, $start_time, $end_time);
    $stmt->execute();
    header("Location: ../../../index.php?p=admin_showtimes");
    exit;
}

/* ========== UPDATE ========== */
if ($action === 'update') {
    $showtime_id = intval($_POST['showtime_id']);
    $movie_id = intval($_POST['movie_id']);
    $room_id = intval($_POST['room_id']);
    $start_time = $_POST['start_time'];
    $end_time = calc_end_time($conn, $movie_id, $start_time);

    // ===== kiểm tra giờ mở cửa và thời điểm đã qua =====
    $hour = intval(date('G', strtotime($start_time)));
    $today = date('Y-m-d');
    $now = time();

    if ($hour < 8) {
        echo "<script>alert('Không thể đặt lịch trước 8:00 sáng (giờ mở cửa)!');history.back();</script>";
        exit;
    }
    if (strtotime($start_time) < $now && date('Y-m-d', strtotime($start_time)) == $today) {
        echo "<script>alert('Không thể sửa suất chiếu về giờ đã trôi qua!');history.back();</script>";
        exit;
    }

    // ===== kiểm tra trùng giờ =====
    if (check_overlap($conn, $room_id, $start_time, $end_time, $showtime_id)) {
        echo "<script>alert('Trùng giờ chiếu với suất khác trong phòng này!');history.back();</script>";
        exit;
    }

    $stmt = $conn->prepare("UPDATE showtimes SET movie_id=?, room_id=?, start_time=?, end_time=? WHERE showtime_id=?");
    $stmt->bind_param("iissi", $movie_id, $room_id, $start_time, $end_time, $showtime_id);
    $stmt->execute();
    header("Location: ../../../index.php?p=admin_showtimes");
    exit;
}

/* ========== DELETE ========== */
if ($action === 'delete') {
    $id = intval($_POST['showtime_id']);
    $stmt = $conn->prepare("DELETE FROM showtimes WHERE showtime_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo "ok";
    exit;
}
?>
