<?php
// checkout_online.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/helpers/order_helper.php';

$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Phương thức không hợp lệ.";
    exit;
}

// ===========================
// 1. Lấy dữ liệu từ form
// ===========================
$user_id     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$showtime_id = isset($_POST['showtime_id']) ? (int)$_POST['showtime_id'] : 0;

// Ghế: dạng chuỗi "10,11,12" hoặc array
$seat_str  = trim($_POST['seats'] ?? '');
$seat_ids  = [];
if (!empty($seat_str)) {
    foreach (explode(',', $seat_str) as $s) {
        $s = (int)trim($s);
        if ($s > 0) $seat_ids[] = $s;
    }
}

// Combo: giả sử form gửi JSON ở input hidden name="combos_json"
$combos_json = $_POST['combos_json'] ?? '[]';
$combo_items = json_decode($combos_json, true);
if (!is_array($combo_items)) $combo_items = [];

if ($user_id <= 0 || $showtime_id <= 0 || empty($seat_ids)) {
    echo "Thiếu dữ liệu user / suất chiếu / ghế.";
    exit;
}

// ===========================
// 2. TÍNH GIÁ (tối thiểu)
// ===========================

// 2.1. Giá vé 1 ghế từ movies.showtimes
$sqlPrice = "
    SELECT m.ticket_price
    FROM showtimes s
    JOIN movies m ON m.movie_id = s.movie_id
    WHERE s.showtime_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sqlPrice);
if (!$stmt) {
    die("SQL ERROR GET PRICE: " . $conn->error);
}
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$rowP = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rowP) {
    echo "Không tìm thấy suất chiếu / phim.";
    exit;
}

$ticket_price = (float)$rowP['ticket_price'];
$ticket_total = $ticket_price * count($seat_ids);

// 2.2. Tính combo_total từ bảng combos
$combo_total = 0;
$normalized_combos = [];

if (!empty($combo_items)) {
    // Lấy giá từ DB để tránh user sửa giá client
    $combo_ids = [];
    foreach ($combo_items as $ci) {
        $cid = (int)($ci['combo_id'] ?? 0);
        if ($cid > 0) $combo_ids[] = $cid;
    }
    $combo_ids = array_unique($combo_ids);

    $prices = [];
    if (!empty($combo_ids)) {
        $placeholders = implode(',', array_fill(0, count($combo_ids), '?'));
        $types       = str_repeat('i', count($combo_ids));

        $sqlComb = "SELECT combo_id, price FROM combos WHERE combo_id IN ($placeholders)";
        $stmtC = $conn->prepare($sqlComb);
        if ($stmtC) {
            $stmtC->bind_param($types, ...$combo_ids);
            $stmtC->execute();
            $resC = $stmtC->get_result();
            while ($r = $resC->fetch_assoc()) {
                $prices[(int)$r['combo_id']] = (float)$r['price'];
            }
            $stmtC->close();
        }
    }

    foreach ($combo_items as $ci) {
        $cid = (int)($ci['combo_id'] ?? 0);
        $qty = (int)($ci['qty'] ?? 0);
        if ($cid <= 0 || $qty <= 0) continue;

        $price = isset($prices[$cid]) ? $prices[$cid] : 0;
        $total = $price * $qty;
        $combo_total += $total;

        $normalized_combos[] = [
            'combo_id' => $cid,
            'qty'      => $qty,
            'price'    => $price
        ];
    }
}

$total_amount = $ticket_total + $combo_total;

// ===========================
// 3. Kiểm tra ghế còn trống
// ===========================
if (!is_seat_available($showtime_id, $seat_ids)) {
    echo "Một hoặc nhiều ghế đã được đặt trước đó. Vui lòng chọn ghế khác.";
    exit;
}

// ===========================
// 4. Tạo payment pending (HÓA ĐƠN TẠM)
// ===========================
$order_code = generate_order_code();

$orderData = [
    'showtime_id'  => $showtime_id,
    'seats'        => $seat_ids,
    'ticket_price' => $ticket_price,
    'ticket_total' => $ticket_total,
    'combos'       => $normalized_combos,
    'combo_total'  => $combo_total,
    'total_amount' => $total_amount
];

$orderDataJson = json_encode($orderData, JSON_UNESCAPED_UNICODE);

$sqlPay = "
    INSERT INTO payments (user_id, ticket_id, method, amount, order_data, status, provider_txn_id, paid_at)
    VALUES (?, NULL, 'online', ?, ?, 'pending', ?, NULL)
";

$stmt = $conn->prepare($sqlPay);
if (!$stmt) {
    die("SQL ERROR INSERT PAYMENT: " . $conn->error);
}

$stmt->bind_param("idss", $user_id, $total_amount, $orderDataJson, $order_code);
$stmt->execute();
$payment_id = $stmt->insert_id;
$stmt->close();

// ===========================
// 5. Lưu session (nếu cần) & Redirect đến trang QR/gateway
// ===========================
$_SESSION['last_payment_id'] = $payment_id;
$_SESSION['last_order_code'] = $order_code;

// Tùy bạn: chuyển sang trang hiển thị QR hoặc redirect gateway
header("Location: payment_qr.php?payment_id=" . $payment_id);
exit;
