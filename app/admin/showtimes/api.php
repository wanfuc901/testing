<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__ . "/../../config/config.php";
require __DIR__ . "/../../models/ShowtimeModel.php";

header("Content-Type: application/json; charset=utf-8");
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'customer')!=='admin') {
  http_response_code(403); echo json_encode(["error"=>"forbidden"]); exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function j($data){ echo json_encode($data); exit; }

switch ($action) {
  case "month_counts": {
    $y = (int)($_GET['year'] ?? date('Y'));
    $m = (int)($_GET['month'] ?? date('n'));
    $start = sprintf("%04d-%02d-01", $y, $m);
    $end   = date("Y-m-t", strtotime($start));
    $sql = "SELECT DATE(start_time) d, COUNT(*) c
            FROM showtimes WHERE DATE(start_time) BETWEEN ? AND ?
            GROUP BY DATE(start_time)";
    $stmt=$conn->prepare($sql); $stmt->bind_param("ss",$start,$end);
    $stmt->execute(); $rs=$stmt->get_result();
    $map=[]; while($r=$rs->fetch_assoc()){ $map[$r['d']] = (int)$r['c']; }
    j(["from"=>$start,"to"=>$end,"counts"=>$map]);
  }

  case "day_list": {
    $date = $_GET['date'] ?? date('Y-m-d');
    $sql = "SELECT sh.showtime_id, m.title, m.poster_url, sh.room_id, sh.start_time, sh.end_time, sh.status
        FROM showtimes sh JOIN movies m ON m.movie_id=sh.movie_id
        WHERE DATE(sh.start_time)=?
        ORDER BY sh.start_time ASC";

    $st=$conn->prepare($sql); $st->bind_param("s",$date); $st->execute();
    $shows=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    foreach ($shows as &$s) {
  $s['poster_url'] = $s['poster_url'] 
      ? '../../views/banners/' . $s['poster_url'] 
      : '../../public/assets/img/noimg.png';
}
unset($s);


    $ex = $conn->prepare("SELECT e.exception_id, m.title, e.room_id, e.start_time, e.end_time, e.reason
                          FROM showtime_exceptions e JOIN movies m ON m.movie_id=e.movie_id
                          WHERE e.date=? ORDER BY e.start_time");
    $ex->bind_param("s",$date); $ex->execute();
    $exc=$ex->get_result()->fetch_all(MYSQLI_ASSOC); $ex->close();

    j(["date"=>$date,"showtimes"=>$shows,"exceptions"=>$exc]);
  }

  case "add_template": {
    $movie_id=(int)$_POST['movie_id'];
    $room_id =(int)$_POST['room_id'];
    $start_time=$_POST['start_time']; $end_time=$_POST['end_time'];
    $dow=$_POST['days_of_week'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
    $start_date=$_POST['start_date']; $end_date=$_POST['end_date'];
    $price=(float)($_POST['price'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    $sql="INSERT INTO showtime_templates(movie_id,room_id,start_time,end_time,days_of_week,start_date,end_date,price,note)
          VALUES(?,?,?,?,?,?,?,?,?)";
    $st=$conn->prepare($sql);
    $st->bind_param("iisssssis",$movie_id,$room_id,$start_time,$end_time,$dow,$start_date,$end_date,$price,$note);
    $st->execute(); j(["ok"=>1,"template_id"=>$st->insert_id]);
  }

  case "add_exception": {
    $movie_id=(int)$_POST['movie_id'];
    $room_id =(int)$_POST['room_id'];
    $date=$_POST['date']; $start_time=$_POST['start_time']; $end_time=$_POST['end_time'];
    $price = isset($_POST['price']) ? (float)$_POST['price'] : null;
    $reason= trim($_POST['reason'] ?? '');
    $sql="INSERT INTO showtime_exceptions(movie_id,room_id,date,start_time,end_time,price,reason)
          VALUES(?,?,?,?,?,?,?)";
    $st=$conn->prepare($sql);
    $st->bind_param("iisssis",$movie_id,$room_id,$date,$start_time,$end_time,$price,$reason);
    $ok=$st->execute();
    if(!$ok){ http_response_code(409); j(["error"=>"conflict_or_invalid","info"=>$conn->error]); }
    j(["ok"=>1,"exception_id"=>$st->insert_id]);
  }

  case "add_showtime": {
    $movie_id=(int)$_POST['movie_id']; $room_id=(int)$_POST['room_id'];
    $date=$_POST['date']; $start_time=$date.' '.$_POST['start_time']; $end_time=$date.' '.$_POST['end_time'];
    if (ShowtimeModel::overlapExists($conn,$room_id,$start_time,$end_time,null)) {
      http_response_code(409); j(["error"=>"overlap"]);
    }
    $st=$conn->prepare("INSERT INTO showtimes(movie_id,room_id,start_time,end_time,status) VALUES(?,?,?,?, 'active')");
    $st->bind_param("iiss",$movie_id,$room_id,$start_time,$end_time);
    $st->execute(); j(["ok"=>1,"showtime_id"=>$st->insert_id]);
  }

  case "delete_showtime": {
    $id=(int)$_POST['showtime_id'];
    $st=$conn->prepare("DELETE FROM showtimes WHERE showtime_id=?"); $st->bind_param("i",$id);
    $st->execute(); j(["ok"=>1]);
  }

  case "reset_day": {
    $date=$_POST['date'];
    // Xóa suất trong ngày
    $st=$conn->prepare("DELETE FROM showtimes WHERE DATE(start_time)=?"); $st->bind_param("s",$date);
    $st->execute();
    // Sinh lại theo template + exception
    $res = ShowtimeModel::generateForDate($conn,$date);
    j(["ok"=>1,"result"=>$res]);
  }

  case "generate_range": {
    $from=$_POST['from']; $to=$_POST['to'];
    $d = strtotime($from); $toT = strtotime($to);
    $sum=0; $skip=[];
    while($d <= $toT){
      $r = ShowtimeModel::generateForDate($conn, date('Y-m-d',$d));
      $sum += $r['created']; $skip = array_merge($skip, $r['skipped']);
      $d = strtotime('+1 day',$d);
    }
    j(["ok"=>1,"created"=>$sum,"skipped"=>$skip]);
  }
  case 'activate_movie':
  $id = intval($_POST['movie_id']);
  $conn->query("UPDATE movies SET status='active' WHERE movie_id=$id");
  echo json_encode(['ok'=>true]);
  break;

  case 'clone_week':
  $from = $_POST['from'] ?? '';
  $to   = $_POST['to']   ?? '';
  if (!$from || !$to) { echo json_encode(['error'=>'Thiếu ngày']); exit; }

  $startFrom = new DateTime($from);
  $startTo   = new DateTime($to);
  $diffDays  = (int)$startTo->diff($startFrom)->days;

  for ($i=0; $i<7; $i++) {
    $srcDate = clone $startFrom; $srcDate->modify("+$i day");
    $dstDate = clone $startTo;   $dstDate->modify("+$i day");

    // Lấy suất chiếu trong ngày gốc
    $q = $conn->prepare("SELECT movie_id, room_id, start_time, end_time FROM showtimes WHERE DATE(start_time)=?");
    $srcDateStr = $srcDate->format('Y-m-d');
    $q->bind_param("s",$srcDateStr);
    $q->execute();
    $res = $q->get_result();

    while($r = $res->fetch_assoc()){
      $st = new DateTime($r['start_time']); $et = new DateTime($r['end_time']);
      $newStart = $dstDate->format('Y-m-d').' '.$st->format('H:i:s');
      $newEnd   = $dstDate->format('Y-m-d').' '.$et->format('H:i:s');

      $ins = $conn->prepare("INSERT INTO showtimes (movie_id, room_id, start_time, end_time, status) VALUES (?,?,?,?, 'active')");
      $ins->bind_param("iiss",$r['movie_id'],$r['room_id'],$newStart,$newEnd);
      $ins->execute();
    }
  }
  echo json_encode(['ok'=>true]);
  break;



  default: http_response_code(400); j(["error"=>"unknown_action"]);
}
