<?php
require_once __DIR__ . '/../../include/auth.php';

header('Content-Type: application/json; charset=utf-8');
vincine_require_admin(true);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ======================================================
   HELPERS
====================================================== */
function calc_end_time(mysqli $conn, int $movie_id, string $start)
{
    $q = $conn->prepare("SELECT duration FROM movies WHERE movie_id=?");
    $q->bind_param("i", $movie_id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $min = intval($row['duration'] ?? 90);
    return date('Y-m-d H:i:s', strtotime($start) + $min * 60);
}

function overlap(mysqli $conn, int $room, string $s, string $e, int $ignore = 0): bool
{
    $sql = "SELECT COUNT(*) c FROM showtimes
            WHERE room_id=?
              AND NOT (end_time <= ? OR start_time >= ?)";
    if ($ignore) $sql .= " AND showtime_id!=?";
    $q = $conn->prepare($sql);
    if ($ignore) $q->bind_param("issi", $room, $s, $e, $ignore);
    else $q->bind_param("iss", $room, $s, $e);
    $q->execute();
    return ($q->get_result()->fetch_assoc()['c'] ?? 0) > 0;
}

/* ======================================================
   ACTIONS
====================================================== */

/* ===== Thống kê số suất theo tháng ===== */
if ($action === 'month_counts') {
    $y = intval($_GET['year']);
    $m = intval($_GET['month']);
    $res = $conn->query("
        SELECT DATE(start_time) d, COUNT(*) c
        FROM showtimes
        WHERE YEAR(start_time)=$y AND MONTH(start_time)=$m
        GROUP BY d
    ");
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[$r['d']] = (int)$r['c'];
    }
    echo json_encode(['counts' => $out]);
    exit;
}

/* ===== Danh sách 1 ngày ===== */
if ($action === 'day_list') {
    $date = $_GET['date'];

    $shows = [];
    $q = $conn->prepare("
        SELECT s.showtime_id, s.room_id, s.start_time, s.end_time, s.status,
               m.title, m.poster_url
        FROM showtimes s
        JOIN movies m ON m.movie_id=s.movie_id
        WHERE DATE(s.start_time)=?
        ORDER BY s.start_time
    ");
    $q->bind_param("s", $date);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $shows[] = $row;

    echo json_encode(['showtimes' => $shows, 'exceptions' => []]);
    exit;
}

/* ===== Thêm suất chiếu ===== */
if ($action === 'add_showtime') {
    $movie = intval($_POST['movie_id']);
    $room  = intval($_POST['room_id']);
    $date  = $_POST['date'];
    $s     = $date . ' ' . $_POST['start_time'] . ':00';
    $e     = calc_end_time($conn, $movie, $s);

    if (overlap($conn, $room, $s, $e)) {
        echo json_encode(['error' => 'overlap']);
        exit;
    }

    $q = $conn->prepare("
        INSERT INTO showtimes(movie_id,room_id,start_time,end_time,status)
        VALUES(?,?,?,?, 'scheduled')
    ");
    $q->bind_param("iiss", $movie, $room, $s, $e);
    $q->execute();

    echo json_encode(['ok' => true]);
    exit;
}

/* ===== Xóa suất ===== */
if ($action === 'delete_showtime') {
    $id = intval($_POST['showtime_id']);
    $q = $conn->prepare("DELETE FROM showtimes WHERE showtime_id=?");
    $q->bind_param("i", $id);
    $q->execute();
    echo json_encode(['ok' => true]);
    exit;
}

/* ===== Sinh 30 ngày ===== */
if ($action === 'generate_range') {
    $from = $_POST['from'] ?? '';
    $to   = $_POST['to'] ?? '';

    if (!$from || !$to) {
        echo json_encode(['error'=>'missing_date']);
        exit;
    }


    $created = 0;
    $movies = $conn->query("SELECT movie_id FROM movies WHERE status='active' LIMIT 1")
                   ->fetch_assoc();
    if (!$movies) {
        echo json_encode(['created'=>0]);
        exit;
    }

    for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
        $date = date('Y-m-d', $d);
        $s = "$date 10:00:00";
        $e = calc_end_time($conn, $movies['movie_id'], $s);
        if (!overlap($conn, 1, $s, $e)) {
            $q = $conn->prepare("
                INSERT INTO showtimes(movie_id,room_id,start_time,end_time,status)
                VALUES(?,?,?,?, 'scheduled')
            ");
            $q->bind_param("iiss", $movies['movie_id'], 1, $s, $e);
            $q->execute();
            $created++;
        }
    }
    echo json_encode(['created' => $created]);
    exit;
}

/* ===== Sao chép tuần ===== */
if ($action === 'clone_week') {
    $from = $_POST['from'];
    $to   = $_POST['to'];
    $diff = (strtotime($to) - strtotime($from)) / 86400;

    $q = $conn->prepare("
        SELECT * FROM showtimes
        WHERE DATE(start_time) BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
    ");
    $q->bind_param("ss", $from, $from);
    $q->execute();
    $r = $q->get_result();

    while ($s = $r->fetch_assoc()) {
        $ns = date('Y-m-d H:i:s', strtotime($s['start_time']) + $diff * 86400);
        $ne = date('Y-m-d H:i:s', strtotime($s['end_time']) + $diff * 86400);
        if (!overlap($conn, $s['room_id'], $ns, $ne)) {
            $ins = $conn->prepare("
              INSERT INTO showtimes(movie_id,room_id,start_time,end_time,status)
              VALUES(?,?,?,?,?)
            ");
            $ins->bind_param(
                "iisss",
                $s['movie_id'],
                $s['room_id'],
                $ns,
                $ne,
                $s['status']
            );
            $ins->execute();
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

/* ===== Reset ngày ===== */
if ($action === 'reset_day') {
    $date = $_POST['date'];
    $q = $conn->prepare("DELETE FROM showtimes WHERE DATE(start_time)=?");
    $q->bind_param("s", $date);
    $q->execute();
    echo json_encode(['ok' => true]);
    exit;
}

/* ===== Kích hoạt phim ===== */
if ($action === 'activate_movie') {
    $id = intval($_POST['movie_id']);
    $q = $conn->prepare("UPDATE movies SET status='active' WHERE movie_id=?");
    $q->bind_param("i", $id);
    $q->execute();
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'invalid_action']);
