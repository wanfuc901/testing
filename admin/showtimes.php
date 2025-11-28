<?php
require_once __DIR__ . '/../app/config/config.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$date = $_GET['date'] ?? date('Y-m-d');

// Lấy danh sách phòng
$rooms = $conn->query("SELECT room_id, name FROM rooms ORDER BY room_id ASC");
$roomList = [];
while($r = $rooms->fetch_assoc()) $roomList[] = $r;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>VinCine · Lịch chiếu <?= htmlspecialchars($date) ?></title>
<link rel="stylesheet" href="public/assets/css/admin.css">
<link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">
<style>

</style>
</head>
<body class="showtime-page">
<div class="admin-wrap">
<div class="admin-container">
  <h1 class="page-title">Lịch chiếu <?= date('d/m/Y',strtotime($date)) ?></h1>

  <form class="date-picker" method="get">
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button type="submit"><i class="bi bi-eye"></i> Xem</button>
  </form>

  <!-- Timeline -->
  <div class="timeline-wrap" id="timeline">
    <div id="time-marker"></div>
    <div id="time-label"></div>

    <div class="timeline-hours">
      <?php for($h=8;$h<=23;$h++): ?>
        <div class="tmark"><?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00</div>
      <?php endfor; ?>
    </div>

    <?php foreach($roomList as $r): ?>
      <div class="room-row" data-room="<?= $r['room_id'] ?>">
        <div class="room-label"><?= htmlspecialchars($r['name']) ?></div>
        <div class="room-track" id="track_<?= $r['room_id'] ?>"></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="legend">Mỗi giờ = 100px. Đường đỏ hiển thị thời gian hiện tại.</div>

  <!-- Form thêm/sửa suất chiếu -->
  <form class="admin-form create" action="app/controllers/admin/showtimes_controller.php" method="post">
    <input type="hidden" name="action" value="create">

    <div class="form-header">
      <h3><i class="bi bi-calendar2-plus"></i> Thêm / Sửa suất chiếu</h3>
    </div>

    <div class="form-grid">
      <div>
        <label><i class="bi bi-film"></i> Phim</label>
        <select name="movie_id" id="movieSelect" required class="input">
          <option value="">-- Chọn phim --</option>
          <?php
          $mv = $conn->query("
            SELECT movie_id, title, duration, status
            FROM movies
            ORDER BY 
              CASE 
                WHEN status='active' THEN 1 
                WHEN status='upcoming' THEN 2 
                ELSE 3 
              END
          ");
          while($m = $mv->fetch_assoc()):
            $status = $m['status'];
            $label = '';
            if($status==='upcoming') $label=' (sắp chiếu)';
            elseif($status==='expired') $label=' (hết hạn)';
          ?>
            <option value="<?= $m['movie_id'] ?>" data-duration="<?= (int)$m['duration'] ?>">
              <?= htmlspecialchars($m['title']).$label ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label><i class="bi bi-building"></i> Phòng chiếu</label>
        <select name="room_id" required class="input">
          <?php foreach($roomList as $r): ?>
            <option value="<?= $r['room_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label><i class="bi bi-calendar"></i> Ngày chiếu</label>
        <input type="date" name="date_show" required class="input">
      </div>

      <div>
        <label><i class="bi bi-clock"></i> Giờ chiếu (24h, ví dụ 08:00 hoặc 21:45)</label>
       <input type="text" name="time_show" maxlength="5" placeholder="HH:MM" pattern="^([01]\d|2[0-3]):[0-5]\d$" required class="input">
      </div>

      <div>
        <label><i class="bi bi-hourglass-split"></i> Thời lượng (phút)</label>
        <input type="number" id="duration" class="input" readonly value="">
      </div>
    </div>

    <div class="form-btns">
      <button type="submit" class="btn primary"><i class="bi bi-save"></i> Thêm</button>
      <button type="reset" class="btn ghost"><i class="bi bi-arrow-repeat"></i> Hủy</button>
    </div>
  </form>
</div>
</div>

<script>
const startBase = 8; // timeline bắt đầu từ 08:00
const colors=["#e53935","#1e88e5","#43a047","#fdd835","#8e24aa","#00acc1","#ff7043"];

loadShows(document.querySelector('input[name="date"]').value);
document.querySelector('.date-picker button').addEventListener('click',e=>{
  e.preventDefault();
  loadShows(document.querySelector('input[name="date"]').value);
});

function parseLocalDateTime(str) {
  // ép MySQL datetime (YYYY-MM-DD HH:MM:SS) về local time VN
  const [d, t] = str.split(' ');
  const [y,m,day] = d.split('-');
  const [h,min,sec] = t.split(':');
  return new Date(y, m-1, day, h, min, sec);
}


function loadShows(date){
  fetch(`app/api/get_showtimes.php?date=${date}`)
    .then(r=>r.json())
    .then(shows=>{
      document.querySelectorAll('.slot').forEach(e=>e.remove());
      shows.forEach((s,i)=>{
        const st=parseLocalDateTime(s.start_time);
const et=parseLocalDateTime(s.end_time);
        const startM=(st.getHours()-startBase)*60+st.getMinutes();
        const dur=(et-st)/60000;
        const track=document.getElementById('track_'+s.room_id);
        if(!track) return;
        const div=document.createElement('div');
        div.className='slot';
        div.style.left=(startM*(100/60))+'px';
        div.style.width=(dur*(100/60))+'px';
        div.style.background=colors[i%colors.length];
        const poster=s.poster_url?`app/views/banners/${s.poster_url}`:'public/assets/img/noimg.png';
        div.innerHTML=`<img src="${poster}" alt=""><span>${s.title}</span>`;
        div.dataset.id=s.showtime_id;
        div.dataset.movie=s.movie_id;
        div.dataset.room=s.room_id;
        div.dataset.start=s.start_time;
        div.dataset.end=s.end_time;
        track.appendChild(div);
        
      });

      
      attachSlotEvents();
      scrollToNow();
      updateSlotColors();

      

      
    });
}



/* ==== Tự động scroll và cập nhật marker thời gian ==== */
function scrollToNow(){
  const now=new Date();
  const hour=now.getHours();
  const min=now.getMinutes();
  const offset=(((hour - startBase) * 60) + min) * (100/60) + 120; // trừ mốc 8h
  const timeline=document.getElementById('timeline');
  if(timeline) timeline.scrollLeft=offset-400>0?offset-400:0;
  const marker=document.getElementById('time-marker');
  const label=document.getElementById('time-label');
  marker.style.left=offset+'px';
  label.style.left=offset+'px';
  label.textContent=`${hour.toString().padStart(2,'0')}:${min.toString().padStart(2,'0')}`;
}
setInterval(scrollToNow,60000);
setInterval(updateSlotColors,60000);
updateSlotColors();
scrollToNow();


function updateSlotColors(){
  // nếu đang xem ngày khác hôm nay thì dùng mốc 23:59 của ngày đó
  const viewedDate = document.querySelector('input[name="date"]').value;
  const todayStr = new Date().toISOString().slice(0,10);
  let compareTime;
  if (viewedDate < todayStr) {
    compareTime = new Date(`${viewedDate}T23:59:59`);
  } else if (viewedDate > todayStr) {
    compareTime = new Date(`${viewedDate}T00:00:00`); // tương lai => không xám
  } else {
    compareTime = new Date(); // hôm nay
  }

  document.querySelectorAll('.slot').forEach(slot=>{
    const end = parseLocalDateTime(slot.dataset.end);
    if(end < compareTime){
      slot.classList.add('past');
    } else {
      slot.classList.remove('past');
    }
  });
}


/* ==== Sự kiện chọn slot ==== */
function attachSlotEvents(){
  document.querySelectorAll('.slot').forEach(slot=>{
    slot.onclick=()=>{
      const f=document.querySelector('form.create');
      f.reset();
      f.querySelector('[name="action"]').value='update';
      f.querySelector('[name="movie_id"]').value=slot.dataset.movie;
      f.querySelector('[name="room_id"]').value=slot.dataset.room;
      const st=parseLocalDateTime(slot.dataset.start);
      f.querySelector('[name="date_show"]').value=st.toISOString().slice(0,10);
      f.querySelector('[name="time_show"]').value=st.toTimeString().slice(0,5);
      if(!f.querySelector('[name="showtime_id"]'))
        f.insertAdjacentHTML('beforeend',`<input type="hidden" name="showtime_id" value="${slot.dataset.id}">`);
      fetch(`app/api/get_movie_duration.php?id=${slot.dataset.movie}`)
        .then(r=>r.json())
        .then(d=>{document.getElementById('duration').value=d.duration||0;});
      const submitBtn=f.querySelector('button[type="submit"]');
      submitBtn.innerHTML='<i class="bi bi-save-fill"></i>';
      submitBtn.title='Lưu thay đổi';
      submitBtn.style.background='var(--gold)';
      submitBtn.style.color='#000';

      let del=f.querySelector('.delete-btn');
      if(!del){
        del=document.createElement('button');
        del.type='button';
        del.className='delete-btn';
        del.innerHTML='<i class="bi bi-trash3-fill"></i>';
        del.title='Xóa suất chiếu';
        f.querySelector('.form-btns').appendChild(del);
      }
      del.onclick=()=>{
        if(confirm('Xác nhận xóa suất chiếu này?')){
          const id=f.querySelector('[name="showtime_id"]').value;
          fetch('app/controllers/admin/showtimes_controller.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=delete&showtime_id=${id}`
          }).then(r=>r.text()).then(()=>{
            alert('Đã xóa suất chiếu.');
            loadShows(document.querySelector('input[name="date"]').value);
            f.reset();
          });
        }
      };
    };
  });
}

/* ==== Cập nhật thời lượng khi chọn phim ==== */
document.getElementById('movieSelect').addEventListener('change',function(){
  const id=this.value;
  if(!id) return;
  fetch(`app/api/get_movie_duration.php?id=${id}`)
    .then(r=>r.json())
    .then(d=>{document.getElementById('duration').value=d.duration||0;});
});

/* ==== Tính start/end khi submit ==== */
document.querySelector('form.create').addEventListener('submit',e=>{
  const date=e.target.date_show.value;
  const time=e.target.time_show.value;
  if(!/^[0-2]\d:[0-5]\d$/.test(time)){
    alert('Giờ chiếu phải đúng định dạng HH:MM (24h).');
    e.preventDefault();return;
  }
  const full=`${date}T${time}`;
  let hidden=e.target.querySelector('[name="start_time"]');
  if(!hidden){
    hidden=document.createElement('input');
    hidden.type='hidden';
    hidden.name='start_time';
    e.target.appendChild(hidden);
  }
  hidden.value=full;
  const start=new Date(hidden.value);
  const dur=parseInt(document.getElementById('duration').value||0);
  if(dur>0){
    const end=new Date(start.getTime()+dur*60000);
    const endStr=end.toISOString().slice(0,16);
    let hiddenEnd=e.target.querySelector('[name="end_time"]');
    if(!hiddenEnd){
      hiddenEnd=document.createElement('input');
      hiddenEnd.type='hidden';
      hiddenEnd.name='end_time';
      e.target.appendChild(hiddenEnd);
    }
    hiddenEnd.value=endStr;
  }
});

/* ==== Reset Form ==== */
document.querySelector('form.create').addEventListener('reset',()=>{
  const f=document.querySelector('form.create');
  f.querySelector('[name="action"]').value='create';
  const submitBtn=f.querySelector('button[type="submit"]');
  submitBtn.innerHTML='<i class="bi bi-plus-circle-fill"></i>';
  submitBtn.title='Thêm suất mới';
  submitBtn.style.background='var(--red)';
  submitBtn.style.color='#fff';
  const id=f.querySelector('[name="showtime_id"]'); if(id) id.remove();
  const del=f.querySelector('.delete-btn'); if(del) del.remove();
});

// ==== Tự động thêm dấu ":" khi nhập giờ ====
const timeInput = document.querySelector('input[name="time_show"]');
timeInput.addEventListener('input', e => {
  let v = e.target.value.replace(/[^0-9]/g,''); // chỉ giữ số
  if (v.length > 4) v = v.slice(0,4);
  if (v.length >= 3) e.target.value = v.slice(0,2) + ':' + v.slice(2);
  else e.target.value = v;
});
</script>
</body>
</html>
