<?php
// helpers/realtime.php

if (!function_exists('realtime_push')) {
    function realtime_push($type, array $data = [])
    {
        // Có thể cho URL vào config nếu muốn
        $url = 'https://vincent-realtime-node.onrender.com/push';


        $payload = json_encode([
            'type' => $type,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 2,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Emit khi thanh toán thành công, ghế chính thức được đặt
 *
 * @param int   $showtime_id
 * @param array $seat_ids (danh sách seat_id)
 */
if (!function_exists('emit_seat_booked_done')) {
    function emit_seat_booked_done($showtime_id, array $seat_ids)
    {
        $showtime_id = (int)$showtime_id;
        $seat_ids    = array_values(array_map('intval', $seat_ids));

        if ($showtime_id <= 0 || empty($seat_ids)) {
            return;
        }

        realtime_push('seat_booked_done', [
            'showtime_id' => $showtime_id,
            'seat_ids'    => $seat_ids,
        ]);
    }
}

function emit_payment_update($payment_id, $status)
{
    realtime_push('payment_update', [
        'payment_id' => (int)$payment_id,
        'status'     => $status
    ]);
}

function emit_payment_new($payment_id, $amount, $user)
{
    realtime_push('payment_new', [
        'payment_id' => (int)$payment_id,
        'amount'     => (int)$amount,
        'user'       => $user
    ]);
}



/**
 * Emit khi admin cập nhật trạng thái vé (paid / confirmed / cancelled)
 */
if (!function_exists('emit_ticket_update')) {
    function emit_ticket_update($ticket_id, $status)
    {
        $ticket_id = (int)$ticket_id;
        $status    = (string)$status;

        if ($ticket_id <= 0 || $status === '') {
            return;
        }

        realtime_push('admin_ticket_update', [
            'ticket_id' => $ticket_id,
            'status'    => $status,
        ]);
    }
}
