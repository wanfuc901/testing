document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("q");
  if (!input) return;

  // Không sửa HTML: set bằng JS
  input.setAttribute("autocomplete", "off");
  const form = input.closest("form") || document.querySelector(".search");
  if (!form) return;
  form.style.position = "relative";

  // Inject CSS (không cần sửa style.css)
  const css = `
  .vc-suggest-box{position:absolute;left:0;right:0;top:100%;margin-top:6px;
    background:var(--card,#15161a);border:1px solid rgba(255,255,255,.1);
    border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.5);z-index:1000;overflow:hidden}
  .vc-suggest-item{display:flex;align-items:center;gap:10px;padding:8px 10px;
    cursor:pointer;border-bottom:1px solid rgba(255,255,255,.05)}
  .vc-suggest-item:last-child{border-bottom:none}
  .vc-suggest-item img{width:40px;height:56px;border-radius:6px;object-fit:cover}
  .vc-suggest-item span{color:var(--text,#f3f4f6);font-weight:500}
  .vc-suggest-item:hover{background:rgba(255,255,255,.08)}
  .vc-noresult{padding:10px;color:#999;text-align:center;font-size:14px}
  `;
  const styleTag = document.createElement("style");
  styleTag.textContent = css;
  document.head.appendChild(styleTag);

  // Hộp gợi ý
  const box = document.createElement("div");
  box.className = "vc-suggest-box";
  box.style.display = "none";
  form.appendChild(box);

  let timer;
  input.addEventListener("input", () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { box.style.display="none"; box.innerHTML=""; return; }

    timer = setTimeout(() => {
      fetch(`/VincentCinemas/app/controllers/ajax_suggest.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(list => {
          box.innerHTML = "";
          if (!Array.isArray(list) || list.length === 0) {
            box.innerHTML = `<div class="vc-noresult">Không tìm thấy phim nào</div>`;
            box.style.display = "block";
            return;
          }
          list.forEach(m => {
            const item = document.createElement("div");
            item.className = "vc-suggest-item";
            item.innerHTML = `<img src="${m.poster}" alt=""><span>${m.title}</span>`;
            item.addEventListener("click", () => {
              window.location.href = `/VincentCinemas/index.php?p=mv&id=${m.id}`;
            });
            box.appendChild(item);
          });
          box.style.display = "block";
        })
        .catch(() => {
          box.innerHTML = `<div class="vc-noresult">Lỗi tải dữ liệu</div>`;
          box.style.display = "block";
        });
    }, 250);
  });

  document.addEventListener("click", e => {
    if (!form.contains(e.target)) { box.style.display="none"; }
  });
});
