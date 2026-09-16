<?php
require_once __DIR__ . '/../../include/require_admin.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../../helpers/realtime.php";
require_once __DIR__ . "/../../../helpers/order_helper.php";

$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) die("No action.");

/* ==========================================================
   1) detail_raw — JSON để IN HÓA ĐƠN
========================================================== */
if ($action === "detail_raw") {

    $id = (int)($_GET['id'] ?? 0);

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

    /* ===== TICKETS ===== */
    $tickets = $conn->query("
        SELECT 
            t.price,
            s.row_number, s.col_number,
            sh.start_time,
            m.title
        FROM tickets t
        JOIN seats s ON s.seat_id = t.seat_id
        JOIN showtimes sh ON sh.showtime_id = t.showtime_id
        JOIN movies m ON m.movie_id = sh.movie_id
        WHERE t.payment_id = $id
    ");

    $ticketHTML = "<table>
        <tr><th>Phim</th><th>Ghế</th><th>Suất</th><th>Giá</th></tr>";

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

        /* ===== COMBOS ===== */
    $combos = $conn->query("
        SELECT 
            pc.qty,
            pc.price,
            pc.total,
            c.name
        FROM payment_combos pc
        JOIN combos c ON c.combo_id = pc.combo_id
        WHERE pc.payment_id = $id
    ");

    $comboHTML = "<table>
        <tr>
            <th>Combo</th>
            <th>SL</th>
            <th>Đơn giá</th>
            <th>Thành tiền</th>
        </tr>";

    while ($cb = $combos->fetch_assoc()) {
        $comboHTML .= "
            <tr>
                <td>{$cb['name']}</td>
                <td>{$cb['qty']}</td>
                <td>" . number_format($cb['price']) . "đ</td>
                <td>" . number_format($cb['total']) . "đ</td>
            </tr>
        ";
    }
    $comboHTML .= "</table>";


    echo json_encode([
        "payment"      => $p,
        "tickets_html" => $ticketHTML,
        "combos_html"  => $comboHTML
    ]);
    exit;
}

if ($action === 'detail' && isset($_GET['id'])) {

    $id = (int)$_GET['id'];

    /* ===== PAYMENT ===== */
    $stmt = $conn->prepare("
        SELECT p.*, c.fullname, c.email
        FROM payments p
        LEFT JOIN customers c ON c.customer_id = p.customer_id
        WHERE p.payment_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pay) die("Không tìm thấy hóa đơn.");

    echo "<p><b>Khách:</b> {$pay['fullname']} ({$pay['email']})</p>";
    echo "<p><b>Mã giao dịch:</b> {$pay['provider_txn_id']}</p>";
    echo "<p><b>Trạng thái:</b> {$pay['status']}</p>";

    /* ======================================================
       TICKETS
    ====================================================== */
    $tickets = $conn->query("
        SELECT 
            t.price,
            s.row_number, s.col_number,
            sh.start_time,
            m.title
        FROM tickets t
        JOIN seats s ON s.seat_id = t.seat_id
        JOIN showtimes sh ON sh.showtime_id = t.showtime_id
        JOIN movies m ON m.movie_id = sh.movie_id
        WHERE t.payment_id = $id
    ");

    echo "<h4>🎟 Danh sách vé</h4>";

    $ticketTotal = 0;

    if ($tickets->num_rows > 0) {

        echo "<table class='admin-table'>
                <tr>
                    <th>Phim</th>
                    <th>Ghế</th>
                    <th>Suất</th>
                    <th>Giá</th>
                </tr>";

        while ($t = $tickets->fetch_assoc()) {

            $seat = chr(64 + (int)$t['row_number']) . $t['col_number'];
            $ticketTotal += (int)$t['price'];

            echo "
                <tr>
                    <td>{$t['title']}</td>
                    <td><b>{$seat}</b></td>
                    <td>{$t['start_time']}</td>
                    <td>" . number_format($t['price']) . "đ</td>
                </tr>
            ";
        }

        echo "</table>";
        echo "<p style='text-align:right'><b>Tổng tiền vé:</b> "
            . number_format($ticketTotal) . "đ</p>";

    } else {
        echo "<p><i>Chưa có vé</i></p>";
    }

    /* ======================================================
       COMBOS
    ====================================================== */
    $combos = $conn->query("
        SELECT 
            pc.qty,
            pc.price,
            pc.total,
            c.name
        FROM payment_combos pc
        JOIN combos c ON c.combo_id = pc.combo_id
        WHERE pc.payment_id = $id
    ");

    echo "<h4>🍿 Danh sách combo</h4>";

    $comboTotal = 0;

    if ($combos->num_rows > 0) {

        echo "<table class='admin-table'>
                <tr>
                    <th>Combo</th>
                    <th>SL</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                </tr>";

        while ($cb = $combos->fetch_assoc()) {

            $comboTotal += (int)$cb['total'];

            echo "
                <tr>
                    <td>{$cb['name']}</td>
                    <td>{$cb['qty']}</td>
                    <td>" . number_format($cb['price']) . "đ</td>
                    <td>" . number_format($cb['total']) . "đ</td>
                </tr>
            ";
        }

        echo "</table>";
        echo "<p style='text-align:right'><b>Tổng tiền combo:</b> "
            . number_format($comboTotal) . "đ</p>";

    } else {
        echo "<p><i>Chưa có combo</i></p>";
    }

    /* ======================================================
       GRAND TOTAL
    ====================================================== */
    $grandTotal = $ticketTotal + $comboTotal;

    echo "
        <hr>
        <p style='text-align:right;font-size:18px'>
            <b>TỔNG CỘNG:</b>
            <span style='color:#e50914'>
                " . number_format($grandTotal) . "đ
            </span>
        </p>
    ";

    exit;
}


/* ==========================================================
   3) ACTION POST (mark_paid / confirm / cancel)
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

    /* ===== MARK PAID ===== */
    case 'mark_paid':

        $stmt = $conn->prepare("
            UPDATE payments SET status='paid'
            WHERE payment_id IN ($in)
        ");
        if (!$stmt) die("SQL ERROR mark_paid: " . $conn->error);

        $stmt->bind_param($type, ...$ids);
        $stmt->execute();
        $stmt->close();

        $newStatus = 'paid';
        break;

    /* ===== CONFIRM ===== */
   case 'confirm':

    foreach ($ids as $pid) {

        // Lấy payment + customer
        $p = $conn->query("
            SELECT p.*, c.email, c.fullname
            FROM payments p
            LEFT JOIN customers c ON c.customer_id = p.customer_id
            WHERE p.payment_id = $pid
        ")->fetch_assoc();

        // CHỈ CONFIRM ONLINE + PAID + CÓ ORDER_DATA
        if (
            !$p
            || $p['status'] !== 'paid'
            || $p['method'] !== 'online'
            || empty($p['order_data'])
        ) {
            continue;
        }


        // 1) Finalize: tạo vé, combo, ghế...
        finalize_payment($pid, $_SESSION['name'] ?? 'admin');

        // 2) Update status SUCCESS
        $conn->query("
            UPDATE payments 
            SET status='success'
            WHERE payment_id = $pid
        ");

        // 3) GỬI MAIL VÉ (SAU KHI SUCCESS)
        if (!empty($p['email'])) {

            // truyền payment_id cho file gửi mail
            $payment_id = $pid;

            require __DIR__ . "/../send_ticket_email.php";
        }

    }

    $newStatus = 'success';
    break;
    /* ===== CANCEL ===== */
    case 'cancel':

        // huỷ payment
        $stmt = $conn->prepare("
            UPDATE payments SET status='fail'
            WHERE payment_id IN ($in)
        ");
        if (!$stmt) die("SQL ERROR cancel: " . $conn->error);

        $stmt->bind_param($type, ...$ids);
        $stmt->execute();
        $stmt->close();

        // huỷ vé
        $conn->query("
            UPDATE tickets
            SET status='cancelled'
            WHERE payment_id IN ($in)
        ");

        // mở ghế
        $conn->query("
            UPDATE seats
            SET temp_locked=0,
                temp_locked_until=NULL
            WHERE seat_id IN (
                SELECT seat_id FROM tickets
                WHERE payment_id IN ($in)
            )
        ");

        $newStatus = 'fail';
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
