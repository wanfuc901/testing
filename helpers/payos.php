<?php
/**
 * Client PayOS.
 *
 * Tài liệu: https://payos.vn/docs/api/
 *
 * Công thức chữ ký (theo đúng tài liệu chính thức):
 *   - Tạo link thanh toán: HMAC_SHA256 trên chuỗi cố định
 *     "amount=..&cancelUrl=..&description=..&orderCode=..&returnUrl=.."
 *   - Webhook: HMAC_SHA256 trên object data đã sort key theo alphabet,
 *     nối thành "key=value&key=value".
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';

if (!defined('PAYOS_API_BASE')) {
    define('PAYOS_API_BASE', 'https://api-merchant.payos.vn');

    /**
     * Giới hạn ký tự của trường description.
     * Tài liệu PayOS: tài khoản ngân hàng không liên kết qua payOS chỉ được 9 ký tự.
     */
    define('PAYOS_DESCRIPTION_MAX', 9);

    /** Thời gian giữ ghế / hiệu lực link thanh toán (giây). */
    define('PAYOS_LINK_TTL', 900);

    define('PAYOS_TIMEOUT', 15);
}

/** Lỗi khi gọi PayOS hoặc dữ liệu trả về không toàn vẹn. */
class PayOSException extends RuntimeException
{
}

/* ====================================================
   CẤU HÌNH
==================================================== */

function vincine_payos_config(): array
{
    return [
        'client_id'    => trim((string)vincine_env('PAYOS_CLIENT_ID', '')),
        'api_key'      => trim((string)vincine_env('PAYOS_API_KEY', '')),
        'checksum_key' => trim((string)vincine_env('PAYOS_CHECKSUM_KEY', '')),
    ];
}

/** Đã cấu hình đủ ba khóa chưa. */
function vincine_payos_enabled(): bool
{
    foreach (vincine_payos_config() as $value) {
        if ($value === '') {
            return false;
        }
    }

    return true;
}

/* ====================================================
   CHỮ KÝ
==================================================== */

/**
 * Chữ ký cho yêu cầu tạo link thanh toán.
 * Thứ tự trường là cố định theo tài liệu, không phải sort động.
 */
function vincine_payos_request_signature(array $body, string $checksumKey): string
{
    $raw = 'amount=' . $body['amount']
         . '&cancelUrl=' . $body['cancelUrl']
         . '&description=' . $body['description']
         . '&orderCode=' . $body['orderCode']
         . '&returnUrl=' . $body['returnUrl'];

    return hash_hmac('sha256', $raw, $checksumKey);
}

/**
 * Chữ ký của một object dữ liệu (dùng cho webhook và response).
 * Chuyển thể đúng theo mã mẫu trong tài liệu PayOS.
 */
function vincine_payos_object_signature(array $data, string $checksumKey): string
{
    ksort($data);

    $parts = [];
    foreach ($data as $key => $value) {
        if ($value === null || $value === 'null' || $value === 'undefined') {
            $value = '';
        } elseif (is_array($value)) {
            $sorted = array_map(
                static function ($element) {
                    if (is_array($element)) {
                        ksort($element);
                    }
                    return $element;
                },
                $value
            );
            $value = json_encode($sorted, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $parts[] = $key . '=' . $value;
    }

    return hash_hmac('sha256', implode('&', $parts), $checksumKey);
}

/* ====================================================
   GỌI API
==================================================== */

/**
 * @param array<string,mixed>|null $body null = GET
 * @return array<string,mixed> phần `data` của response
 * @throws PayOSException
 */
function vincine_payos_request(string $method, string $path, ?array $body = null): array
{
    $config = vincine_payos_config();

    if (!vincine_payos_enabled()) {
        throw new PayOSException('Chưa cấu hình PayOS (PAYOS_CLIENT_ID / PAYOS_API_KEY / PAYOS_CHECKSUM_KEY).');
    }

    $ch = curl_init(PAYOS_API_BASE . $path);
    if ($ch === false) {
        throw new PayOSException('Không khởi tạo được kết nối tới PayOS.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => PAYOS_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'x-client-id: ' . $config['client_id'],
            'x-api-key: ' . $config['api_key'],
            'Content-Type: application/json',
        ],
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $options);

    try {
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
    } finally {
        curl_close($ch);
    }

    if ($raw === false) {
        throw new PayOSException('Không gọi được PayOS: ' . $err);
    }

    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        throw new PayOSException('PayOS trả về dữ liệu không hợp lệ (HTTP ' . $code . ').');
    }

    // PayOS luôn trả code "00" khi thành công, kể cả với HTTP 200.
    if (($payload['code'] ?? '') !== '00') {
        throw new PayOSException(
            'PayOS từ chối yêu cầu: ' . ($payload['code'] ?? '?') . ' - ' . ($payload['desc'] ?? 'không rõ')
        );
    }

    $data = $payload['data'] ?? null;
    if (!is_array($data)) {
        throw new PayOSException('PayOS không trả về trường data.');
    }

    /* Response cũng có chữ ký: kiểm tra để phát hiện dữ liệu bị can thiệp. */
    $signature = (string)($payload['signature'] ?? '');
    if ($signature !== '') {
        $expected = vincine_payos_object_signature($data, $config['checksum_key']);
        if (!hash_equals($expected, $signature)) {
            throw new PayOSException('Chữ ký response PayOS không khớp.');
        }
    }

    return $data;
}

/* ====================================================
   CÁC THAO TÁC
==================================================== */

/**
 * Tạo link thanh toán.
 *
 * @param int    $orderCode   Số nguyên dương, duy nhất vĩnh viễn cho mỗi kênh.
 * @param int    $amount      VND, số nguyên.
 * @param string $description Tối đa PAYOS_DESCRIPTION_MAX ký tự.
 * @return array<string,mixed> gồm qrCode, checkoutUrl, paymentLinkId, bin, accountNumber...
 * @throws PayOSException
 */
function vincine_payos_create_link(
    int $orderCode,
    int $amount,
    string $description,
    string $returnUrl,
    string $cancelUrl,
    ?int $expiredAt = null,
    array $extra = []
): array {
    $config = vincine_payos_config();

    $body = [
        'orderCode'   => $orderCode,
        'amount'      => $amount,
        'description' => mb_substr($description, 0, PAYOS_DESCRIPTION_MAX),
        'returnUrl'   => $returnUrl,
        'cancelUrl'   => $cancelUrl,
    ];

    // Chữ ký phải tính trên đúng 5 trường trên, trước khi thêm trường phụ.
    $signature = vincine_payos_request_signature($body, $config['checksum_key']);

    if ($expiredAt !== null) {
        $body['expiredAt'] = $expiredAt;
    }

    foreach (['buyerName', 'buyerEmail', 'buyerPhone', 'items'] as $key) {
        if (isset($extra[$key])) {
            $body[$key] = $extra[$key];
        }
    }

    $body['signature'] = $signature;

    return vincine_payos_request('POST', '/v2/payment-requests', $body);
}

/**
 * Tra cứu trạng thái. $id là orderCode hoặc paymentLinkId.
 */
function vincine_payos_get_link($id): array
{
    return vincine_payos_request('GET', '/v2/payment-requests/' . rawurlencode((string)$id));
}

function vincine_payos_cancel_link($id, ?string $reason = null): array
{
    $body = $reason === null ? [] : ['cancellationReason' => $reason];

    return vincine_payos_request('POST', '/v2/payment-requests/' . rawurlencode((string)$id) . '/cancel', $body);
}

/**
 * Đăng ký (hoặc cập nhật) webhook URL với PayOS.
 * PayOS sẽ gọi thử URL này, nó phải trả về HTTP 200.
 */
function vincine_payos_confirm_webhook(string $webhookUrl): array
{
    return vincine_payos_request('POST', '/confirm-webhook', ['webhookUrl' => $webhookUrl]);
}

/**
 * Xác thực payload webhook.
 *
 * @param array<string,mixed> $payload toàn bộ body JSON đã decode
 * @return array<string,mixed>|null data nếu hợp lệ, null nếu chữ ký sai
 */
function vincine_payos_verify_webhook(array $payload): ?array
{
    $data      = $payload['data'] ?? null;
    $signature = (string)($payload['signature'] ?? '');

    if (!is_array($data) || $signature === '') {
        return null;
    }

    $expected = vincine_payos_object_signature($data, vincine_payos_config()['checksum_key']);

    return hash_equals($expected, $signature) ? $data : null;
}
