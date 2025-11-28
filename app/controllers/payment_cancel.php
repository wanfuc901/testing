<script>
let timeLeft = 300; // 5 phút = 300 giây

const countdownBox = document.createElement("div");
countdownBox.style.cssText =
    "margin-top:15px;font-size:16px;font-weight:600;color:#f5c518;";
document.querySelector(".payment-card").appendChild(countdownBox);

function updateClock() {
    const m = Math.floor(timeLeft / 60).toString().padStart(2, "0");
    const s = (timeLeft % 60).toString().padStart(2, "0");
    countdownBox.innerHTML = "Thời gian thanh toán: <span>"+m+":"+s+"</span>";

    if (timeLeft <= 0) {
        autoCancel();
        return;
    }
    timeLeft--;
}
updateClock();
let timer = setInterval(updateClock, 1000);

// ===========================
// Hủy giao dịch khi hết giờ
// ===========================
function autoCancel() {
    clearInterval(timer);

    fetch("../app/controllers/payment_cancel.php", { method: "POST" })
        .then(r => r.text())
        .then(txt => {
            console.log("Cancel:", txt);
            window.location.href = "../../index.php?timeout=1";
        });
}
</script>
