<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../../helpers/realtime.php";
require_once __DIR__ . "/../../../helpers/order_helper.php";

$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) die("No action.");

/* ==========================================================
   1) detail_raw — API JSON để IN HÓA ĐƠN
========================================================== */
if ($action === "detail_raw") {

    $id = intval($_GET['id']);

    // Lấy payment
    $p = $conn->query("
        SELECT p.*, c.fullname, c.email
        FROM payments p
        LEFT JOIN customers c ON c.customer_id = p.customer_id
        WHERE p.payment_id = $id
    ")->fetch_assoc();

    if (!$p) {
        echo json_encode(["error" => "NOT_FOUND"]);
        exit;
    }

    /* ===== LẤY DANH SÁCH VÉ ===== */
    $qTickets = "
        SELECT 
            t.ticket_id,
            t.price,
            s.row_number,
            s.col_number,
            sh.start_time,
            m.title
        FROM tickets t
        JOIN seats s ON s.seat_id = t.seat_id
        JOIN showtimes sh ON sh.showtime_id = t.showtime_id
        JOIN movies m ON m.movie_id = sh.movie_id
        WHERE t.payment_id = $id
    ";

    $tickets = $conn->query($qTickets);
    if (!$tickets) die("SQL ERROR TICKETS: " . $conn->error . "\nQUERY:\n" . $qTickets);

    $ticketHTML = "<table>
        <tr>
            <th>Phim</th>
            <th>Ghế</th>
            <th>Suất</th>
            <th>Giá</th>
        </tr>";

    while ($t = $tickets->fetch_assoc()) {
        $ticketHTML .= "
            <tr>
                <td>{$t['title']}</td>
                <td>Hàng {$t['row_number']} - Ghế {$t['col_number']}</td>
                <td>{$t['start_time']}</td>
                <td>" . number_format($t['price']) . "đ</td>
            </tr>
        ";
    }

    $ticketHTML .= "</table>";

    /* ===== LẤY DANH SÁCH COMBO ===== */
    $qCombos = "
        SELECT 
            pc.qty,
            c.name,
            c.price
        FROM payment_combos pc
        JOIN combos c ON c.combo_id = pc.combo_id
        WHERE pc.payment_id = $id
    ";

    $combos = $conn->query($qCombos);
    if (!$combos) die("SQL ERROR COMBOS: " . $conn->error . "\nQUERY:\n" . $qCombos);

    $comboHTML = "<table>
        <tr>
            <th>Combo</th>
            <th>Số lượng</th>
            <th>Giá</th>
        </tr>";

    while ($cb = $combos->fetch_assoc()) {
        $comboHTML .= "
            <tr>
                <td>{$cb['name']}</td>
                <td>{$cb['qty']}</td>
                <td>" . number_format($cb['price'] * $cb['qty']) . "đ</td>
            </tr>
        ";
    }

    $comboHTML .= "</table>";

    /* ===== TRẢ JSON ===== */
    echo json_encode([
        "payment"      => $p,
        "tickets_html" => $ticketHTML,
        "combos_html"  => $comboHTML
    ]);
    exit;
}

/* ==========================================================
   2) detail — popup hiển thị HTML trong trang admin
========================================================== */
if ($action === 'detail' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $sql = "
        SELECT p.*, c.fullname, c.email
        FROM payments p
        LEFT JOIN customers c ON c.customer_id = p.customer_id
        WHERE p.payment_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL ERROR DETAIL: " . $conn->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pay) die("Không tìm thấy hóa đơn.");

    /* ===== LẤY VÉ ===== */
    $sqlTickets = "
        SELECT 
            t.price,
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
    if (!$stmtT) die("SQL ERROR TICKETS(detail): " . $conn->error);
    $stmtT->bind_param("i", $id);
    $stmtT->execute();
    $tickets = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtT->close();

    /* ===== LẤY COMBO ===== */
    $sqlCombo = "
        SELECT pc.qty, pc.total, c.name
        FROM payment_combos pc
        JOIN combos c ON c.combo_id = pc.combo_id
        WHERE pc.payment_id = ?
    ";

    $stmtC = $conn->prepare($sqlCombo);
    if (!$stmtC) die("SQL ERROR COMBO(detail): " . $conn->error);
    $stmtC->bind_param("i", $id);
    $stmtC->execute();
    $combos = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtC->close();

    /* ===== HIỂN THỊ HTML POPUP ===== */
    echo "<h2>Hóa đơn #{$id}</h2>";
    echo "<p><strong>Khách:</strong> {$pay['fullname']} ({$pay['email']})</p>";
    echo "<p><strong>Mã đơn:</strong> {$pay['provider_txn_id']}</p>";
    echo "<p><strong>Số tiền:</strong> " . number_format($pay['amount']) . "đ</p>";
    echo "<p><strong>Trạng thái:</strong> {$pay['status']}</p>";
    echo "<hr>";

    echo "<h3>Danh sách vé</h3><ul>";
    if ($tickets) {
        foreach ($tickets as $t) {
            $seat = chr(64 + $t['row_number']) . $t['col_number'];
            echo "<li><b>{$seat}</b> — {$t['title']} ({$t['room_name']}) — "
               . number_format($t['price']) . "đ</li>";
        }
    } else echo "<li>Không có vé.</li>";
    echo "</ul>";

    echo "<h3>Combo</h3><ul>";
    if ($combos) {
        foreach ($combos as $c) {
            echo "<li>{$c['name']} × {$c['qty']} — "
               . number_format($c['total']) . "đ</li>";
        }
    } else echo "<li>Không có combo.</li>";
    echo "</ul>";

    echo "<button onclick=\"document.getElementById('popup').style.display='none'\" class='btn danger'>Đóng</button>";
    exit;
}

/* ==========================================================
   3) CÁC ACTION CẦN POST (confirm / mark_paid / cancel)
========================================================== */
$ids = [];
if (!empty($_POST['payments'])) {
    foreach ($_POST['payments'] as $id) {
        if (is_numeric($id)) $ids[] = (int)$id;
    }
}

if (!$ids) die("No bills selected.");

$in   = implode(",", array_fill(0, count($ids), '?'));
$type = str_repeat('i', count($ids));

switch ($action) {

    case 'mark_paid':
        $sql = "UPDATE payments SET status='success' WHERE payment_id IN ($in)";
        $newStatus = "success";
        $stmt = $conn->prepare($sql);
        if (!$stmt) die("SQL ERROR mark_paid: " . $conn->error);
        $stmt->bind_param($type, ...$ids);
        $stmt->execute();
        $stmt->close();
        break;

    case 'confirm':
        foreach ($ids as $pid) {
            try {
                finalize_payment($pid, $_SESSION['name'] ?? 'admin');
            } catch (Exception $e) {}
        }
        $newStatus = "success";
        break;

    case 'cancel':
        $sql = "UPDATE payments SET status='fail' WHERE payment_id IN ($in)";
        $newStatus = "fail";
        $stmt = $conn->prepare($sql);
        if (!$stmt) die("SQL ERROR cancel: " . $conn->error);
        $stmt->bind_param($type, ...$ids);
        $stmt->execute();
        $stmt->close();
        break;

    default:
        die("Invalid action.");
}

/* ===== REALTIME ===== */
foreach ($ids as $pid) {
    emit_payment_update($pid, $newStatus);
}

/* ===== REDIRECT ===== */
header("Location: ../../../index.php?p=admin_payments&msg=done");
exit;
