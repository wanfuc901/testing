<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../../helpers/realtime.php";

$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) die("No action.");

/* ============================================================
   CHI TIẾT HÓA ĐƠN (popup)
============================================================ */
if ($action === 'detail' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    /* Lấy thông tin payment */
    $sql = "
        SELECT p.*, u.name AS fullname, u.email
        FROM payments p
        LEFT JOIN users u ON u.user_id = p.user_id
        WHERE p.payment_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL ERROR PREPARE DETAIL: " . $conn->error);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pay) die("Không tìm thấy hóa đơn.");

    /* ============================================================
       LẤY DANH SÁCH VÉ — tickets.payment_id
    ============================================================= */
    $tickets = [];

    $sqlTickets = "
        SELECT 
            t.*, 
            s.row_number, s.col_number,
            st.start_time, st.end_time,
            m.title,
            r.name AS room_name
        FROM tickets t
        JOIN seats s ON s.seat_id = t.seat_id
        JOIN showtimes st ON st.showtime_id = t.showtime_id
        JOIN movies m ON m.movie_id = st.movie_id
        JOIN rooms r ON r.room_id = st.room_id
        WHERE t.payment_id = ?
    ";

    $stmtT = $conn->prepare($sqlTickets);
    if (!$stmtT) die("SQL ERROR TICKETS: " . $conn->error);

    $stmtT->bind_param("i", $id);
    $stmtT->execute();
    $tickets = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtT->close();


    /* ============================================================
       LẤY DANH SÁCH COMBO — payment_combos
       (CỘT ĐÚNG: qty, price, total)
    ============================================================= */
    $combos = [];

    $sqlCombo = "
        SELECT 
            pc.qty,
            pc.price,
            pc.total,
            c.name
        FROM payment_combos pc
        JOIN combos c ON c.combo_id = pc.combo_id
        WHERE pc.payment_id = ?
    ";

    $stmtC = $conn->prepare($sqlCombo);
    if (!$stmtC) die("SQL ERROR COMBO: " . $conn->error);

    $stmtC->bind_param("i", $id);
    $stmtC->execute();
    $combos = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtC->close();


    /* ============================================================
       OUTPUT HTML POPUP
    ============================================================= */
    echo "<h2>Hóa đơn #{$id}</h2>";
    echo "<p><strong>Khách:</strong> ".htmlspecialchars($pay['fullname'])." (".htmlspecialchars($pay['email']).")</p>";
    echo "<p><strong>Mã đơn:</strong> {$pay['provider_txn_id']}</p>";
    echo "<p><strong>Số tiền:</strong> ".number_format($pay['amount'])."đ</p>";
    echo "<p><strong>Trạng thái:</strong> {$pay['status']}</p>";
    echo "<hr>";

    /* VÉ */
    echo "<h3>Danh sách vé</h3><ul>";
    if ($tickets) {
        foreach ($tickets as $t) {
            $label = chr(64 + $t['row_number']) . $t['col_number'];
            echo "<li><b>{$label}</b> — {$t['title']} ({$t['room_name']}) - "
               . number_format($t['price']) . "đ</li>";
        }
    } else {
        echo "<li>Không có vé.</li>";
    }
    echo "</ul>";

    /* COMBO */
    echo "<h3>Combo</h3><ul>";
    if ($combos) {
        foreach ($combos as $c) {
            echo "<li>{$c['name']} × {$c['qty']} — "
               . number_format($c['total']) . "đ</li>";
        }
    } else {
        echo "<li>Không có combo.</li>";
    }
    echo "</ul>";

    echo "<button onclick=\"document.getElementById('popup').style.display='none'\" class='btn danger'>Đóng</button>";
    exit;
}

/* ============================================================
   XỬ LÝ HÀNH ĐỘNG HÓA ĐƠN
============================================================ */
$ids = [];
if (!empty($_POST['payments'])) {
    foreach ($_POST['payments'] as $id) {
        if (is_numeric($id)) $ids[] = (int)$id;
    }
}

if (!$ids) die("No bills selected.");

$in = implode(",", array_fill(0, count($ids), '?'));
$type = str_repeat('i', count($ids));

switch ($action) {

    case 'mark_paid':
        $sql = "UPDATE payments SET status='success' WHERE payment_id IN ($in)";
        $newStatus = "success";
        break;

    case 'confirm':
        $sql = "UPDATE payments SET status='success' WHERE payment_id IN ($in)";
        $newStatus = "success";
        break;

    case 'cancel':
        $sql = "UPDATE payments SET status='fail' WHERE payment_id IN ($in)";
        $newStatus = "fail";
        break;

    default:
        die("Invalid action.");
}


$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL ERROR PREPARE ACTION: " . $conn->error);

$stmt->bind_param($type, ...$ids);
$stmt->execute();
$stmt->close();

/* PUSH REALTIME */
foreach ($ids as $pid) {
    emit_payment_update($pid, $newStatus);

}

header("Location: ../../../index.php?p=admin_payments&msg=done");
exit;
