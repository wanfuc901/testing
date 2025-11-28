<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__ . "/../../config/config.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'customer')!=='admin') {
  header("Location:index.php?p=login"); exit;
}

// Lấy danh sách phim
$movies = $conn->query("SELECT movie_id, title, poster_url, status FROM movies ORDER BY release_date DESC");
$movieList = [];
while($m = $movies->fetch_assoc()) $movieList[] = $m;

// Lấy danh sách phòng
$rooms = $conn->query("SELECT room_id, name FROM rooms ORDER BY room_id");
$roomList = [];
while($r = $rooms->fetch_assoc()) $roomList[] = $r;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>VinCine · Quản lý lịch chiếu</title>
  <link rel="stylesheet" href="public/assets/css/admin.css">
  <link rel="stylesheet" href="public/assets/css/admin_scheduler.css">
  <link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">
</head>

<body class="admin-page">
<div class="admin-wrap">
  <div class="admin-container">

    <!-- ===== Header ===== -->
    <div class="admin-title">
      <h1><i class="bi bi-calendar3-week"></i> Lịch chiếu</h1>
      <div class="admin-actions">
        <a href="index.php?p=admin_dashboard" class="btn ghost"><i class="bi bi-speedometer2"></i> Bảng điều khiển</a>
      </div>
    </div>

    <!-- ===== Tools ===== -->
    <div class="sched-head" style="margin-bottom:20px;">
      <div class="tools" style="flex-wrap:wrap;gap:8px;align-items:center;">
        <button id="btnPrev"><i class="bi bi-chevron-left"></i> Tháng trước</button>
        <strong id="ym" style="font-size:16px;color:var(--red);font-weight:700"></strong>
        <button id="btnNext">Tháng sau <i class="bi bi-chevron-right"></i></button>
        <button id="btnGen30"><i class="bi bi-magic"></i> Sinh 30 ngày tới</button>
      </div>
    </div>

    <!-- ===== Calendar Grid ===== -->
    <div id="calendar" class="calendar-grid"></div>

    <!-- ===== Sao chép tuần ===== -->
    <div class="admin-form" style="margin-top:30px;">
      <h3><i class="bi bi-calendar-range"></i> Sao chép tuần</h3>
      <div class="form-grid">
        <div>
          <label><i class="bi bi-calendar3"></i> Từ ngày</label>
          <input type="date" id="cw_from" class="input">
        </div>
        <div>
          <label><i class="bi bi-calendar3"></i> Đến ngày</label>
          <input type="date" id="cw_to" class="input">
        </div>
      </div>
      <div class="form-btns">
        <button id="btnCloneWeek" class="btn primary"><i class="bi bi-copy"></i> Sao chép</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Drawer ngày ===== -->
<div id="dayDrawer" class="drawer">
  <div class="drawer-head">
    <strong id="drawerDate"></strong>
    <button id="drawerClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="drawer-scroller">
    <h3><i class="bi bi-film"></i> Suất chiếu</h3>
    <table class="vin-table" id="tblShows">
      <thead><tr><th>Poster</th><th>Phim</th><th>Phòng</th><th>Giờ</th><th>TT</th><th></th></tr></thead>
      <tbody></tbody>
    </table>

    <h3><i class="bi bi-slash-circle"></i> Exception trong ngày</h3>
    <table class="vin-table" id="tblEx">
      <thead><tr><th>Giờ</th><th>Phim</th><th>Phòng</th><th>Lý do</th></tr></thead>
      <tbody></tbody>
    </table>

    <!-- ===== Thêm suất chiếu nhanh ===== -->
    <div class="admin-form">
      <h3><i class="bi bi-plus-circle"></i> Thêm suất chiếu nhanh</h3>
      <div class="form-grid">
        <div>
          <label><i class="bi bi-film"></i> Phim</label>
          <select id="f_movie" class="movie-select input">
            <option value="">-- Chọn phim --</option>
            <?php foreach($movieList as $m): ?>
              <option value="<?= $m['movie_id'] ?>"
                data-status="<?= $m['status'] ?>"
                data-poster="app/views/banners/<?= htmlspecialchars($m['poster_url']) ?>"
                class="<?= $m['status']!=='active' ? 'inactive' : '' ?>">
                <?= htmlspecialchars($m['title']) ?><?= $m['status']!=='active' ? ' (Chưa kích hoạt)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label><i class="bi bi-building"></i> Phòng chiếu</label>
          <select id="f_room" class="input">
            <option value="">-- Phòng --</option>
            <?php foreach($roomList as $r): ?>
              <option value="<?= $r['room_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label><i class="bi bi-clock"></i> Bắt đầu</label>
          <input id="f_s" type="time" value="10:00" class="input">
        </div>
        <div>
          <label><i class="bi bi-clock-history"></i> Kết thúc</label>
          <input id="f_e" type="time" value="12:00" class="input">
        </div>
      </div>

      <div class="form-btns">
        <button id="btnAddShow" class="btn primary"><i class="bi bi-save"></i> Thêm</button>
        <button id="btnResetDay" class="btn ghost"><i class="bi bi-arrow-repeat"></i> Reset Template</button>
      </div>
    </div>

    <!-- ===== Thêm Exception ===== -->
    <div class="admin-form">
      <h3><i class="bi bi-tools"></i> Thêm Exception (ghi đè)</h3>
      <div class="form-grid">
        <div>
          <label><i class="bi bi-film"></i> Phim</label>
          <select id="x_movie" class="input">
            <option value="">-- Chọn phim --</option>
            <?php foreach($movieList as $m): ?>
              <option value="<?= $m['movie_id'] ?>" data-poster="app/views/banners/<?= htmlspecialchars($m['poster_url']) ?>">
                <?= htmlspecialchars($m['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label><i class="bi bi-building"></i> Phòng chiếu</label>
          <select id="x_room" class="input">
            <option value="">-- Phòng --</option>
            <?php foreach($roomList as $r): ?>
              <option value="<?= $r['room_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label><i class="bi bi-clock"></i> Giờ bắt đầu</label>
          <input id="x_s" type="time" value="21:00" class="input">
        </div>
        <div>
          <label><i class="bi bi-clock-history"></i> Giờ kết thúc</label>
          <input id="x_e" type="time" value="23:00" class="input">
        </div>
        <div class="full">
          <label><i class="bi bi-chat-left-text"></i> Lý do</label>
          <input id="x_reason" placeholder="Ví dụ: Sự kiện đặc biệt, bảo trì, v.v." class="input">
        </div>
      </div>
      <div class="form-btns">
        <button id="btnAddEx" class="btn primary"><i class="bi bi-plus-lg"></i> Thêm Exception</button>
      </div>
    </div>
  </div>
</div>

<script>
const api = (p,method='GET',body=null)=>fetch('app/admin/showtimes/api.php' + (method==='GET'?'?'+new URLSearchParams(p):''), {
  method,
  headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
  body: method==='POST'? new URLSearchParams(p):null
}).then(r=>r.json());

let cur = new Date(); cur.setDate(1);
function ymd(d){return d.toISOString().slice(0,10);}
function renderMonth(){
  const y = cur.getFullYear(), m = cur.getMonth();
  document.getElementById('ym').textContent = `Tháng ${m+1}/${y}`;
  const cal = document.getElementById('calendar'); cal.innerHTML='';
  const first = new Date(y,m,1);
  const startWeekDay = (first.getDay()+6)%7;
  const last = new Date(y,m+1,0);
  const total = startWeekDay + last.getDate();
  const rows = Math.ceil(total/7);
  for(let r=0;r<rows;r++){
    const row = document.createElement('div'); row.className='cal-row';
    for(let c=0;c<7;c++){
      const cell = document.createElement('div'); cell.className='cal-cell';
      const idx = r*7+c;
      const dayNum = idx-startWeekDay+1;
      if(dayNum>=1 && dayNum<=last.getDate()){
        const d = new Date(y,m,dayNum);
        const ds = y+'-'+String(m+1).padStart(2,'0')+'-'+String(dayNum).padStart(2,'0');
        const today = new Date();
        const isToday = dayNum===today.getDate() && m===today.getMonth() && y===today.getFullYear();
        cell.innerHTML = `<div class="date">${dayNum}</div>
                          <div class="count" id="ct_${ds}">–</div>
                          <button class="btnDay" data-d="${ds}">Chi tiết</button>`;
        if(isToday) cell.classList.add('today');
      }
      row.appendChild(cell);
    }
    cal.appendChild(row);
  }
  api({action:'month_counts',year:y,month:m+1}).then(j=>{
    Object.entries(j.counts||{}).forEach(([d,c])=>{
      const el = document.getElementById('ct_'+d); if(el) el.textContent = c+' suất';
    });
  });
}
renderMonth();

document.getElementById('btnPrev').onclick=()=>{cur.setMonth(cur.getMonth()-1);renderMonth();}
document.getElementById('btnNext').onclick=()=>{cur.setMonth(cur.getMonth()+1);renderMonth();}
document.getElementById('btnGen30').onclick=()=>{
  const from = ymd(new Date());
  const to = ymd(new Date(Date.now()+29*86400000));
  api({action:'generate_range',from,to},'POST').then(j=>{renderMonth(); alert('Đã sinh: '+j.created);});
};

document.addEventListener('click',e=>{
  const b = e.target.closest('.btnDay');
  if(b){ openDay(b.dataset.d); }
});
const drawer = document.getElementById('dayDrawer');
document.getElementById('drawerClose').onclick=()=>drawer.classList.remove('open');

let curDate = null;
function openDay(d){
  curDate=d;
  document.getElementById('drawerDate').textContent = 'Ngày '+d.split('-').reverse().join('/');
  drawer.classList.add('open');
  loadDay();
}
function loadDay(){
  api({action:'day_list',date:curDate}).then(j=>{
    const tb = document.querySelector('#tblShows tbody'); tb.innerHTML='';
    (j.showtimes||[]).forEach(s=>{
      const poster = s.poster_url 
        ? `app/views/banners/${s.poster_url}` 
        : `public/assets/img/noimg.png`;
      const st = new Date(s.start_time).toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit',hour12:false});
      const et = new Date(s.end_time).toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit',hour12:false});
      const tr = document.createElement('tr');
      tr.innerHTML = `<td><img src="${poster}" class="poster-mini"></td>
                      <td>${s.title}</td>
                      <td>P${s.room_id}</td>
                      <td>${st}–${et}</td>
                      <td>${s.status}</td>
                      <td><button data-id="${s.showtime_id}" class="delShow btn ghost"><i class="bi bi-trash"></i></button></td>`;
      tb.appendChild(tr);
    });
    const te = document.querySelector('#tblEx tbody'); te.innerHTML='';
    (j.exceptions||[]).forEach(x=>{
      te.innerHTML += `<tr><td>${x.start_time}–${x.end_time}</td><td>${x.title}</td><td>P${x.room_id}</td><td>${x.reason||''}</td></tr>`;
    });
  });
}

document.addEventListener('click',e=>{
  if(e.target.closest('.delShow')){
    const id=e.target.closest('.delShow').dataset.id;
    api({action:'delete_showtime',showtime_id:id},'POST').then(()=>loadDay());
  }
});

document.getElementById('btnAddShow').onclick=()=>{
  api({
    action:'add_showtime', date:curDate,
    movie_id: document.getElementById('f_movie').value,
    room_id : document.getElementById('f_room').value,
    start_time: document.getElementById('f_s').value,
    end_time  : document.getElementById('f_e').value
  },'POST').then(r=>{
    if(r.error==='overlap') alert('Trùng giờ phòng.');
    loadDay();
  });
};

document.getElementById('f_movie').addEventListener('change', e=>{
  const opt = e.target.selectedOptions[0];
  if(opt && opt.dataset.status!=='active'){
    if(confirm(`Phim "${opt.text}" chưa kích hoạt. Bạn có muốn kích hoạt ngay?`)){
      api({action:'activate_movie', movie_id: opt.value},'POST').then(r=>{
        if(r.ok){
          alert('Đã kích hoạt phim.');
          opt.dataset.status='active';
          opt.classList.remove('inactive');
          opt.textContent = opt.textContent.replace(' (Chưa kích hoạt)','');
        }
      });
    } else {
      e.target.value='';
    }
  }
});

document.getElementById('btnResetDay').onclick=()=>{
  api({action:'reset_day',date:curDate},'POST').then(()=>loadDay());
};
document.getElementById('btnAddEx').onclick=()=>{
  api({
    action:'add_exception',
    date:curDate,
    movie_id: document.getElementById('x_movie').value,
    room_id : document.getElementById('x_room').value,
    start_time: document.getElementById('x_s').value,
    end_time  : document.getElementById('x_e').value,
    reason    : document.getElementById('x_reason').value
  },'POST').then(()=>loadDay());
};
document.getElementById('btnCloneWeek').onclick=()=>{
  const f=document.getElementById('cw_from').value;
  const t=document.getElementById('cw_to').value;
  if(!f || !t){ alert('Chọn ngày bắt đầu và ngày dán.'); return; }
  if(confirm(`Sao chép lịch tuần bắt đầu ${f} sang tuần bắt đầu ${t}?`)){
    api({action:'clone_week',from:f,to:t},'POST').then(r=>{
      if(r.ok){ alert('Đã sao chép xong.'); renderMonth(); }
    });
  }
};
</script>
</body>
</html>
