<?php
if (!defined('VIN_APP')) define('VIN_APP', 1);
require_once __DIR__ . "/../config/config.php";

class ShowtimeModel {
  public static function overlapExists(mysqli $conn, int $roomId, string $start, string $end, ?int $excludeId = null): bool {
    $sql = "SELECT 1 FROM showtimes 
            WHERE room_id=? 
              AND ((start_time < ? AND end_time > ?) OR (start_time >= ? AND start_time < ?))
              " . ($excludeId ? "AND showtime_id<>?" : "") . "
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($excludeId) $stmt->bind_param("issssi", $roomId, $end, $start, $start, $end, $excludeId);
    else           $stmt->bind_param("issss",  $roomId, $end, $start, $start, $end);
    $stmt->execute(); $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
  }

  public static function generateForDate(mysqli $conn, string $date): array {
    $dow = date('D', strtotime($date)); // Mon..Sun
    $created = 0; $skipped = [];

    // 1) Exceptions trước, tạo thẳng
    $q1 = $conn->prepare("SELECT movie_id, room_id, start_time, end_time, COALESCE(price, m.ticket_price) as price
                          FROM showtime_exceptions e 
                          JOIN movies m ON m.movie_id=e.movie_id
                          WHERE e.date=?");
    $q1->bind_param("s", $date);
    $q1->execute();
    $ex = $q1->get_result();
    while ($r = $ex->fetch_assoc()) {
      $start = $date . " " . $r['start_time'];
      $end   = $date . " " . $r['end_time'];
      if (self::overlapExists($conn, (int)$r['room_id'], $start, $end, null)) {
        $skipped[] = ["reason"=>"overlap","room_id"=>$r['room_id'],"start"=>$start,"end"=>$end]; 
        continue;
      }
      $ins = $conn->prepare("INSERT INTO showtimes (movie_id, room_id, start_time, end_time, status) VALUES (?,?,?,?, 'active')");
      $ins->bind_param("iiss", $r['movie_id'], $r['room_id'], $start, $end);
      $ins->execute(); $ins->close(); $created++;
    }
    $q1->close();

    // 2) Templates còn hiệu lực, khớp dow, không bị exception trùng slot
    $q2 = $conn->prepare("SELECT t.movie_id, t.room_id, t.start_time, t.end_time, COALESCE(t.price, m.ticket_price) price
                          FROM showtime_templates t
                          JOIN movies m ON m.movie_id=t.movie_id
                          WHERE t.is_active=1
                            AND ? BETWEEN t.start_date AND t.end_date
                            AND FIND_IN_SET(?, t.days_of_week)");
    $q2->bind_param("ss", $date, $dow);
    $q2->execute();
    $tpl = $q2->get_result();
    while ($r = $tpl->fetch_assoc()) {
      // Nếu đã có exception y hệt, bỏ qua
      $chk = $conn->prepare("SELECT 1 FROM showtime_exceptions 
                             WHERE room_id=? AND date=? AND start_time=? AND end_time=? LIMIT 1");
      $chk->bind_param("isss", $r['room_id'], $date, $r['start_time'], $r['end_time']);
      $chk->execute(); $chk->store_result();
      $hasEx = $chk->num_rows>0; $chk->close();
      if ($hasEx) continue;

      $start = $date . " " . $r['start_time'];
      $end   = $date . " " . $r['end_time'];
      if (self::overlapExists($conn, (int)$r['room_id'], $start, $end, null)) {
        $skipped[] = ["reason"=>"overlap","room_id"=>$r['room_id'],"start"=>$start,"end"=>$end]; 
        continue;
      }
      $ins = $conn->prepare("INSERT INTO showtimes (movie_id, room_id, start_time, end_time, status) VALUES (?,?,?,?, 'active')");
      $ins->bind_param("iiss", $r['movie_id'], $r['room_id'], $start, $end);
      $ins->execute(); $ins->close(); $created++;
    }
    $q2->close();

    return ["created"=>$created, "skipped"=>$skipped];
  }
}
