<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../../helpers/realtime.php";
require_once __DIR__ . "/../../../helpers/order_helper.php";

$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) die("No action.");

/* ============================================================
   📌 XEM CHI TIẾT HÓA ĐƠN (POPUP)
============================================================ */
if ($action === 'detail' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    /* Lấy payment */
    $sql = "
        SELECT p.*, u.name AS fullname, u.email
        FROM payments p
        LEFT JOIN users u ON u.user_id = p.user_id
        WHERE p.payment_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL ERROR DETAIL: " . $conn->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pay) die("Không tìm thấy hóa đơn.");

    /* Lấy vé */
    $sqlTicket = "
        SELECT 
            t.*, s.row_number, s.col_number,
            st.start_time, st.end_time,
            m.title, r.name AS room_name
        FROM tickets t
        JOIN seats s ON s.seat_id = t.seat_id
        JOIN showtimes st ON st.showtime_id = t.showtime_id
        JOIN movies m ON m.movie_id = st.movie_id
        JOIN rooms r ON r.room_id = st.room_id
        WHERE t.payment_id = ?
    ";
    $stmtT = $conn->prepare($sqlTicket);
    $stmtT->bind_param("i", $id);
    $stmtT->execute();
    $tickets = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtT->close();

    /* Lấy combo */
    $sqlCombo = "
        SELECT pc.qty, pc.price, pc.total, c.name
        FROM payment_combos pc
        JOIN combos c ON c.combo_id = pc.combo_id
        WHERE pc.payment_id = ?
    ";
    $stmtC = $conn->prepare($sqlCombo);
    $stmtC->bind_param("i", $id);
    $stmtC->execute();
    $combos = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtC->close();

    /* Render popup */
    echo "<h2>Hóa đơn #{$id}</h2>";
    echo "<p><strong>Khách:</strong> " . htmlspecialchars($pay['fullname']) . " (" . htmlspecialchars($pay['email']) . ")</p>";
    echo "<p><strong>Mã đơn:</strong> {$pay['provider_txn_id']}</p>";
    echo "<p><strong>Số tiền:</strong> " . number_format($pay['amount']) . "đ</p>";
    echo "<p><strong>Trạng thái:</strong> {$pay['status']}</p>";
    echo "<hr>";

    echo "<h3>Danh sách vé</h3><ul>";
    if ($tickets) {
        foreach ($tickets as $t) {
            $label = chr(64 + $t['row_number']) . $t['col_number'];
            echo "<li><b>$label</b> – {$t['title']} ({$t['room_name']}) – " . number_format($t['price']) . "đ</li>";
        }
    } else {
        echo "<li>Chưa có vé.</li>";
    }
    echo "</ul>";

    echo "<h3>Combo</h3><ul>";
    if ($combos) {
        foreach ($combos as $c) {
            echo "<li>{$c['name']} × {$c['qty']} – " . number_format($c['total']) . "đ</li>";
        }
    } else {
        echo "<li>Không có combo.</li>";
    }
    echo "</ul>";

    echo "<button onclick=\"document.getElementById('popup').style.display='none'\" class='btn danger'>Đóng</button>";
    exit;
}

/* ============================================================
   📌 XỬ LÝ HÀNH ĐỘNG (mark_paid / confirm / cancel)
============================================================ */

/* Lấy danh sách id */
$ids = [];
if (!empty($_POST['payments'])) {
    foreach ($_POST['payments'] as $p) {
        if (is_numeric($p)) $ids[] = (int)$p;
    }
}
if (empty($ids)) die("Không có hóa đơn nào được chọn.");

$newStatus = null;

switch ($action) {

    /* --------------------------------------------------
       ADMIN ĐÁNH DẤU ĐÃ THANH TOÁN (NHƯNG KHÔNG TẠO VÉ)
    -------------------------------------------------- */
    case 'mark_paid':
        $in = implode(",", array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql = "UPDATE payments SET status='success', paid_at=NOW() WHERE payment_id IN ($in)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $stmt->close();

        $newStatus = "success";
        break;

    /* --------------------------------------------------
       CONFIRM = TẠO VÉ THẬT BẰNG finalize_payment()
    -------------------------------------------------- */
    case 'confirm':
        foreach ($ids as $pid) {
            try {
                finalize_payment($pid, $_SESSION['name'] ?? 'admin');
            } catch (Exception $e) {
                // Không die để xử lý nhiều bill
                error_log("Finalize payment thất bại bill $pid: " . $e->getMessage());
            }
        }
        $newStatus = "success";
        break;

    /* --------------------------------------------------
       CANCEL HÓA ĐƠN
    -------------------------------------------------- */
    case 'cancel':
        $in = implode(",", array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql = "UPDATE payments SET status='fail' WHERE payment_id IN ($in)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $stmt->close();

        $newStatus = "fail";
        break;

    default:
        die("Invalid action.");
}

/* Emit realtime update */
foreach ($ids as $pid) {
    emit_payment_update($pid, $newStatus);
}

header("Location: ../../../index.php?p=admin_payments&msg=done");
exit;
?>
