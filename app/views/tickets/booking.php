<?php
include __DIR__ . "/../../config/config.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['showtime_id']) || !is_numeric($_GET['showtime_id'])) {
    die("Suất chiếu không hợp lệ");
}

$showtime_id = intval($_GET['showtime_id']);

// ===== Lấy suất chiếu + thông tin =====
$sql = "SELECT s.showtime_id, s.start_time, s.end_time, s.room_id,
               r.name AS room_name, r.seat_rows, r.seat_cols,
               m.title
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.room_id
        JOIN movies m ON m.movie_id = s.movie_id
        WHERE s.showtime_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$showtime = $stmt->get_result()->fetch_assoc();
if (!$showtime) die("Không tìm thấy suất chiếu");

// ===== Xác định tên cột =====
$colNames = [];
$resCols = $conn->query("SHOW COLUMNS FROM seats");
while ($r = $resCols->fetch_assoc()) $colNames[] = $r['Field'];

$rowCol = in_array("row_number", $colNames) ? "row_number" :
         (in_array("row", $colNames) ? "row" :
         (in_array("seat_row", $colNames) ? "seat_row" : null));

$colCol = in_array("col_number", $colNames) ? "col_number" :
         (in_array("col", $colNames) ? "col" :
         (in_array("seat_col", $colNames) ? "seat_col" : null));

if (!$rowCol || !$colCol) die("Không xác định được cấu trúc ghế");

// ===== Lấy ghế =====
$seats = [];
$q = $conn->prepare("SELECT seat_id, `$rowCol` AS rownum, `$colCol` AS colnum FROM seats WHERE room_id=?");
$q->bind_param("i", $showtime['room_id']);
$q->execute();
$r = $q->get_result();
while ($s = $r->fetch_assoc()) {
    $seats[$s['rownum']][$s['colnum']] = $s['seat_id'];
}

// ===== Lấy ghế đã đặt / đang giữ =====
$booked = [];
$q2 = $conn->prepare("
    SELECT seat_id 
    FROM tickets 
    WHERE showtime_id = ?
      AND status IN ('pending','paid','confirmed')
");
$q2->bind_param("i", $showtime_id);
$q2->execute();
$r2 = $q2->get_result();
while ($b = $r2->fetch_assoc()) $booked[] = $b['seat_id'];
$q2->close();

// ===== Giá vé =====
$pq = $conn->prepare("SELECT m.ticket_price 
                      FROM movies m JOIN showtimes s ON m.movie_id=s.movie_id 
                      WHERE s.showtime_id=?");
$pq->bind_param("i", $showtime_id);
$pq->execute();
$pq->bind_result($ticket_price);
$pq->fetch();
$pq->close();
if (!$ticket_price) $ticket_price = 80000;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đặt vé - <?= htmlspecialchars($showtime['title']) ?></title>

  <link rel="stylesheet" href="public/assets/css/style.css">

  <style>
    .seat.locked {
        background: #999 !important;
        opacity: 0.5;
        cursor: not-allowed !important;
    }
  </style>

  <script src="https://vincent-realtime-node.onrender.com/socket.io/socket.io.js"></script>
</head>

<body>

<div class="booking-layout">

    <div class="theater">
        <h2>Chọn ghế</h2>
        <div class="screen">Màn hình</div>

        <div class="seats">
        <?php
        $rows = $showtime['seat_rows'];
        $cols = $showtime['seat_cols'];

        for ($i = 1; $i <= $rows; $i++): ?>
            <div class="seat-row">

                <?php for ($j = 1; $j <= $cols; $j++):
                    $seatId = $seats[$i][$j] ?? null;
                    $isBooked = $seatId && in_array($seatId, $booked);
                ?>
                    <button class="seat <?= $isBooked ? 'booked' : '' ?>"
                            data-seat-id="<?= $seatId ?>"
                            <?= $isBooked ? 'disabled' : '' ?>>
                        <?= chr(64+$i) . $j ?>
                    </button>
                <?php endfor; ?>

            </div>
        <?php endfor; ?>
        </div>

        <div class="legend">
            <span><span class="box free"></span> Trống</span>
            <span><span class="box booked"></span> Đã đặt</span>
            <span><span class="box selected"></span> Bạn chọn</span>
        </div>
    </div>


    <aside class="booking-summary">
        <h2>Thông tin đặt vé</h2>

        <p><strong>Phim:</strong> <?= htmlspecialchars($showtime['title']) ?></p>
        <p><strong>Phòng:</strong> <?= htmlspecialchars($showtime['room_name']) ?></p>
        <p><strong>Suất:</strong>
            <?= date("H:i", strtotime($showtime['start_time'])) ?> -
            <?= date("H:i", strtotime($showtime['end_time'])) ?>
        </p>

        <div id="ticketInfo">
            <p><strong>Ghế:</strong> <span id="selectedSeatsList">-</span></p>
            <p><strong>Số vé:</strong> <span id="ticketCount">0</span></p>
            <p><strong>Giá vé:</strong> 
                <span id="ticketPrice"><?= number_format($ticket_price) ?> đ</span>
            </p>
            <p class="total"><strong>Tổng tiền:</strong> <span id="totalPrice">0 đ</span></p>
        </div>

        <form method="post" action="index.php?p=cbs">
            <input type="hidden" name="showtime_id" value="<?= $showtime_id ?>">
            <input type="hidden" name="seats" id="selectedSeats">
            <button type="submit" class="btn-book">Thanh toán</button>
        </form>
    </aside>

</div>

<script>
// ==================================================
// KẾT NỐI NODE
// ==================================================
const socket = io("https://vincent-realtime-node.onrender.com", {
    transports: ["websocket"]
});

const showID = <?= $showtime_id ?>;
socket.emit("join_showtime", showID);

// ==================================================
// XỬ LÝ GHẾ LOCAL + REALTIME
// ==================================================
const seatButtons = document.querySelectorAll(".seat:not(.booked)");
const selectedSeatIds = [];
const seatListEl = document.getElementById("selectedSeatsList");
const ticketCountEl = document.getElementById("ticketCount");
const totalPriceEl = document.getElementById("totalPrice");
const ticketPrice =
  parseInt(document.getElementById("ticketPrice").innerText.replace(/\D/g, "")) || 80000;

// ------- click ghế (NGƯỜI DÙNG) --------
seatButtons.forEach(seat => {
  seat.addEventListener("click", () => {

    const id = seat.dataset.seatId;
    if (!id) return;

    // ------------------------------
    // Nếu CHỌN ghế
    // ------------------------------
    if (!seat.classList.contains("selected")) {

        // Gửi Node lock cho người khác
        socket.emit("select_seat", {
            showtime_id: showID,
            seat_id: id
        });

        // Người đang chọn → KHÔNG LOCK
        seat.classList.add("selected");
        selectedSeatIds.push(id);

    } 
    // ------------------------------
    // Nếu BỎ CHỌN ghế
    // ------------------------------
    else {
        socket.emit("unselect_seat", {
            showtime_id: showID,
            seat_id: id
        });

        seat.classList.remove("selected");
        selectedSeatIds.splice(selectedSeatIds.indexOf(id), 1);
    }

    // update hidden input
    document.getElementById("selectedSeats").value =
        selectedSeatIds.join(",");

    // update label
    const labels = Array.from(
        document.querySelectorAll(".seat.selected")
    ).map(s => s.innerText);

    seatListEl.textContent = labels.length ? labels.join(", ") : "-";

    ticketCountEl.textContent = labels.length;
    totalPriceEl.textContent =
      (labels.length * ticketPrice).toLocaleString("vi-VN") + " đ";
  });
});


// ==================================================
// REALTIME TỪ NODE (cho người khác)
// ==================================================

// Người khác chọn → lock ghế
socket.on("seat_locked", (seatId) => {
    const btn = document.querySelector(`button[data-seat-id="${seatId}"]`);
    if (!btn) return;

    // Nếu CHÍNH MÌNH đang chọn ghế này → KHÔNG LOCK
    if (selectedSeatIds.includes(seatId)) return;

    btn.classList.add("locked");
    btn.disabled = true;
});

// Người khác bỏ chọn → unlock
socket.on("seat_unlocked", (seatId) => {
    const btn = document.querySelector(`button[data-seat-id="${seatId}"]`);
    if (!btn) return;

    // Nếu chính mình đang chọn → không mở
    if (selectedSeatIds.includes(seatId)) return;

    btn.classList.remove("locked");
    btn.disabled = false;
});

// Khi thanh toán thành công → BOOK vĩnh viễn
socket.on("seat_booked_done", (data) => {
    data.seat_ids.forEach(seatId => {
        const btn = document.querySelector(`button[data-seat-id="${seatId}"]`);
        if (!btn) return;

        btn.classList.remove("locked");
        btn.classList.add("booked");
        btn.disabled = true;
    });
});
</script>

</body>
</html>