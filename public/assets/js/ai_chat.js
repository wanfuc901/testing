document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("chat-form");
  if (!form) return; // nếu không có khung chat thì dừng
  const input = document.getElementById("chat-input");
  const box = document.getElementById("chat-messages");

  // ==== Gửi tin nhắn ====
  form.addEventListener("submit", async e => {
    e.preventDefault();
    const msg = input.value.trim();
    if (!msg) return;
    appendMsg("user", msg);
    input.value = "";
    try {
      const res = await fetch("app/api/ai_booking.php", {
        method: "POST",
        body: new URLSearchParams({ message: msg })
      });
      const data = await res.json();
      appendMsg("bot", data.reply);
    } catch (err) {
      appendMsg("bot", "Lỗi hệ thống, vui lòng thử lại.");
      console.error("AI Chat error:", err);
    }
  });

  // ==== Hiển thị tin nhắn ====
  function appendMsg(who, text) {
    const div = document.createElement("div");
    div.className = "msg " + who;
    div.innerHTML = text.replace(/\n/g, "<br>");
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  }

  // ==== Bắt sự kiện click các nút ====
  document.addEventListener("click", e => {
    // --- nút gợi ý ---
    if (e.target.matches(".ai-suggest button")) {
      const msg = e.target.dataset.msg;
      input.value = msg;
      form.dispatchEvent(new Event("submit"));
    }

    // --- nút chọn phim sau khi bấm "Đặt vé nhanh" ---
    if (e.target.matches(".ai-btn")) {
      const movie = e.target.dataset.movie;
      input.value = "Đặt vé phim " + movie;
      form.dispatchEvent(new Event("submit"));
    }
  });

  // ==== Khởi tạo nội dung mặc định ====
  appendMsg(
    "bot",
    "Xin chào! Tôi có thể giúp bạn nhanh các việc sau:" +
      "<div class='ai-suggest'>" +
      "<button data-msg='Phim hay nhất hôm nay'>🎥 Phim hay hôm nay</button>" +
      "<button data-msg='Hôm nay chiếu phim gì'>📅 Lịch chiếu hôm nay</button>" +
      "<button data-msg='Mai có phim gì'>🌙 Phim chiếu ngày mai</button>" +
      "<button data-msg='Đặt vé nhanh'>🎟️ Đặt vé nhanh</button>" +
      "<button data-msg='Giá vé và khuyến mãi hiện nay'>💰 Giá vé / ưu đãi</button>" +
      "</div>"
  );
});
