<?php
/**
 * KARTLY - Payment Gateway Helpers
 */

require_once __DIR__ . '/../config/database.php';

function paymentBoolSetting(string $key, bool $default = false): bool
{
    $value = getSetting($key);
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function paymentStringSetting(string $key, string $default = ''): string
{
    $value = getSetting($key);
    if ($value === null) {
        return $default;
    }

    return trim((string)$value);
}

function paymentAbsoluteUrl(string $path, array $query = []): string
{
    $base = rtrim(SITE_URL, '/');
    $url = $base . '/' . ltrim($path, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function paymentHttpRequest(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 45): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status_code' => 0,
            'body' => '',
            'json' => null,
            'error' => 'cURL extension is not enabled in PHP.',
        ];
    }

    $method = strtoupper(trim($method));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, max(5, $timeout));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return [
            'ok' => false,
            'status_code' => $statusCode,
            'body' => '',
            'json' => null,
            'error' => $curlError ?: 'Unknown HTTP transport error.',
        ];
    }

    $json = null;
    $trimmed = trim($raw);
    if ($trimmed !== '' && ($trimmed[0] ?? '') === '{') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return [
        'ok' => $statusCode >= 200 && $statusCode < 300,
        'status_code' => $statusCode,
        'body' => $raw,
        'json' => $json,
        'error' => null,
    ];
}

function paymentEnsureAttemptsTable(PDO $db): bool
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS payment_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                gateway VARCHAR(32) NOT NULL,
                gateway_payment_id VARCHAR(255) NULL,
                gateway_transaction_id VARCHAR(255) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'initiated',
                request_payload LONGTEXT NULL,
                response_payload LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_payment_attempts_order_id (order_id),
                INDEX idx_payment_attempts_gateway (gateway),
                INDEX idx_payment_attempts_gateway_payment_id (gateway_payment_id),
                CONSTRAINT fk_payment_attempts_order
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function paymentCreateAttempt(
    PDO $db,
    int $orderId,
    string $gateway,
    ?string $gatewayPaymentId,
    string $status,
    array $requestPayload = [],
    array $responsePayload = []
): ?int {
    if (!paymentEnsureAttemptsTable($db)) {
        return null;
    }

    $stmt = $db->prepare("
        INSERT INTO payment_attempts (
            order_id, gateway, gateway_payment_id, status, request_payload, response_payload
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $gateway,
        $gatewayPaymentId,
        $status,
        !empty($requestPayload) ? json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        !empty($responsePayload) ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);

    return (int)$db->lastInsertId();
}

function paymentUpdateAttempt(PDO $db, int $attemptId, array $data): void
{
    if ($attemptId <= 0 || !paymentEnsureAttemptsTable($db)) {
        return;
    }

    $set = [];
    $params = [];
    if (array_key_exists('gateway_transaction_id', $data)) {
        $set[] = 'gateway_transaction_id = ?';
        $params[] = $data['gateway_transaction_id'];
    }
    if (array_key_exists('status', $data)) {
        $set[] = 'status = ?';
        $params[] = $data['status'];
    }
    if (array_key_exists('response_payload', $data)) {
        $set[] = 'response_payload = ?';
        $payload = $data['response_payload'];
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $params[] = $payload;
    }
    if (array_key_exists('gateway_payment_id', $data)) {
        $set[] = 'gateway_payment_id = ?';
        $params[] = $data['gateway_payment_id'];
    }

    if (empty($set)) {
        return;
    }

    $params[] = $attemptId;
    $stmt = $db->prepare("UPDATE payment_attempts SET " . implode(', ', $set) . " WHERE id = ?");
    $stmt->execute($params);
}

function paymentFindAttemptByGatewayPaymentId(PDO $db, string $gateway, string $gatewayPaymentId): ?array
{
    if (!paymentEnsureAttemptsTable($db)) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT *
        FROM payment_attempts
        WHERE gateway = ? AND gateway_payment_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$gateway, $gatewayPaymentId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function paymentGetOrderById(PDO $db, int $orderId): ?array
{
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function paymentGetOrderByNumber(PDO $db, string $orderNumber): ?array
{
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function paymentAppendOrderNote(PDO $db, int $orderId, string $note): void
{
    $note = trim($note);
    if ($orderId <= 0 || $note === '') {
        return;
    }

    $stmt = $db->prepare("SELECT notes FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $existing = $stmt->fetchColumn();
    $timestampedNote = '[' . date('Y-m-d H:i:s') . '] ' . $note;
    $newNote = trim((string)$existing) !== ''
        ? trim((string)$existing) . "\n" . $timestampedNote
        : $timestampedNote;

    $update = $db->prepare("UPDATE orders SET notes = ? WHERE id = ?");
    $update->execute([$newNote, $orderId]);
}

function paymentMarkOrderPaid(PDO $db, int $orderId, string $gateway, ?string $transactionId, string $note = ''): void
{
    $stmt = $db->prepare("
        UPDATE orders
        SET payment_status = 'paid',
            status = CASE WHEN status IN ('pending', 'cancelled') THEN 'processing' ELSE status END,
            transaction_id = COALESCE(NULLIF(?, ''), transaction_id)
        WHERE id = ?
    ");
    $stmt->execute([$transactionId, $orderId]);

    if ($note !== '') {
        paymentAppendOrderNote($db, $orderId, $note);
    }
}

function paymentMarkOrderFailed(PDO $db, int $orderId, string $gateway, string $note, bool $cancelOrder = false): void
{
    $status = $cancelOrder ? 'cancelled' : 'pending';
    $stmt = $db->prepare("
        UPDATE orders
        SET payment_status = 'failed',
            status = CASE WHEN status = 'delivered' THEN status ELSE ? END
        WHERE id = ?
    ");
    $stmt->execute([$status, $orderId]);
    paymentAppendOrderNote($db, $orderId, $note);
}

function paymentClearUserCart(PDO $db, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
}

function paymentGetSslCommerzConfig(): array
{
    $sandbox = paymentBoolSetting('payment_sslcommerz_sandbox', true);

    return [
        'enabled' => paymentBoolSetting('payment_sslcommerz_enabled', true),
        'sandbox' => $sandbox,
        'store_id' => paymentStringSetting('payment_sslcommerz_store_id'),
        'store_passwd' => paymentStringSetting('payment_sslcommerz_store_password'),
        'api_url' => $sandbox
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php',
        'validation_url' => $sandbox
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php',
    ];
}

function paymentStartSslCommerz(PDO $db, array $order, string $preferredChannel = ''): array
{
    $cfg = paymentGetSslCommerzConfig();
    if (!$cfg['enabled']) {
        return ['success' => false, 'error' => 'SSLCOMMERZ is disabled.'];
    }
    if ($cfg['store_id'] === '' || $cfg['store_passwd'] === '') {
        return ['success' => false, 'error' => 'SSLCOMMERZ credentials are missing in admin settings.'];
    }

    $payload = [
        'store_id' => $cfg['store_id'],
        'store_passwd' => $cfg['store_passwd'],
        'total_amount' => number_format((float)$order['total'], 2, '.', ''),
        'currency' => 'BDT',
        'tran_id' => $order['order_number'],
        'success_url' => paymentAbsoluteUrl('payment_callback.php', ['gateway' => 'sslcommerz', 'action' => 'success']),
        'fail_url' => paymentAbsoluteUrl('payment_callback.php', ['gateway' => 'sslcommerz', 'action' => 'fail']),
        'cancel_url' => paymentAbsoluteUrl('payment_callback.php', ['gateway' => 'sslcommerz', 'action' => 'cancel']),
        'ipn_url' => paymentAbsoluteUrl('payment_ipn.php', ['gateway' => 'sslcommerz']),
        'cus_name' => trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? '')),
        'cus_email' => $order['shipping_email'] ?? '',
        'cus_add1' => $order['shipping_address'] ?? '',
        'cus_city' => $order['shipping_city'] ?? '',
        'cus_postcode' => $order['shipping_postal_code'] ?? '',
        'cus_country' => $order['shipping_country'] ?? 'Bangladesh',
        'cus_phone' => $order['shipping_phone'] ?? '',
        'shipping_method' => 'YES',
        'ship_name' => trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? '')),
        'ship_add1' => $order['shipping_address'] ?? '',
        'ship_city' => $order['shipping_city'] ?? '',
        'ship_postcode' => $order['shipping_postal_code'] ?? '',
        'ship_country' => $order['shipping_country'] ?? 'Bangladesh',
        'product_name' => 'KARTLY Order ' . $order['order_number'],
        'product_category' => 'Ecommerce',
        'product_profile' => 'general',
    ];

    if ($preferredChannel !== '') {
        $payload['multi_card_name'] = strtolower($preferredChannel);
    }

    $response = paymentHttpRequest(
        'POST',
        $cfg['api_url'],
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query($payload)
    );

    $json = is_array($response['json']) ? $response['json'] : [];
    $gatewayPageUrl = trim((string)($json['GatewayPageURL'] ?? ''));
    $attemptId = paymentCreateAttempt(
        $db,
        (int)$order['id'],
        'sslcommerz',
        trim((string)($json['sessionkey'] ?? '')),
        $gatewayPageUrl !== '' ? 'redirected' : 'failed',
        $payload,
        $json
    );

    if ($gatewayPageUrl === '') {
        if ($attemptId) {
            paymentUpdateAttempt($db, $attemptId, ['status' => 'failed', 'response_payload' => $json]);
        }
        $reason = trim((string)($json['failedreason'] ?? 'Unable to initialize SSLCOMMERZ checkout.'));
        return ['success' => false, 'error' => $reason];
    }

    return [
        'success' => true,
        'redirect_url' => $gatewayPageUrl,
        'attempt_id' => $attemptId,
    ];
}

function paymentValidateSslCommerz(string $valId): array
{
    $cfg = paymentGetSslCommerzConfig();
    if ($cfg['store_id'] === '' || $cfg['store_passwd'] === '') {
        return ['success' => false, 'error' => 'Missing SSLCOMMERZ credentials for validation.'];
    }
    if (trim($valId) === '') {
        return ['success' => false, 'error' => 'Missing SSLCOMMERZ validation ID.'];
    }

    $url = $cfg['validation_url'] . '?' . http_build_query([
        'val_id' => $valId,
        'store_id' => $cfg['store_id'],
        'store_passwd' => $cfg['store_passwd'],
        'format' => 'json',
        'v' => 1,
    ]);

    $response = paymentHttpRequest('GET', $url);
    $json = is_array($response['json']) ? $response['json'] : [];
    $status = strtoupper(trim((string)($json['status'] ?? '')));
    $isValid = in_array($status, ['VALID', 'VALIDATED'], true);

    if (!$isValid) {
        return [
            'success' => false,
            'error' => trim((string)($json['error'] ?? 'SSLCOMMERZ validation failed.')),
            'data' => $json,
        ];
    }

    return [
        'success' => true,
        'data' => $json,
    ];
}

function paymentGetBkashConfig(): array
{
    $sandbox = paymentBoolSetting('payment_bkash_sandbox', true);
    $base = $sandbox
        ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout'
        : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';

    return [
        'enabled' => paymentBoolSetting('payment_bkash_enabled', false),
        'sandbox' => $sandbox,
        'app_key' => paymentStringSetting('payment_bkash_app_key'),
        'app_secret' => paymentStringSetting('payment_bkash_app_secret'),
        'username' => paymentStringSetting('payment_bkash_username'),
        'password' => paymentStringSetting('payment_bkash_password'),
        'base_url' => $base,
    ];
}

function paymentBkashGrantToken(array $cfg): array
{
    $required = ['app_key', 'app_secret', 'username', 'password'];
    foreach ($required as $key) {
        if (trim((string)($cfg[$key] ?? '')) === '') {
            return ['success' => false, 'error' => 'bKash credentials are incomplete.'];
        }
    }

    $payload = json_encode([
        'app_key' => $cfg['app_key'],
        'app_secret' => $cfg['app_secret'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $response = paymentHttpRequest('POST', $cfg['base_url'] . '/token/grant', [
        'Content-Type: application/json',
        'Accept: application/json',
        'username: ' . $cfg['username'],
        'password: ' . $cfg['password'],
    ], $payload);

    $json = is_array($response['json']) ? $response['json'] : [];
    $token = trim((string)($json['id_token'] ?? ''));

    if ($token === '') {
        $message = trim((string)($json['statusMessage'] ?? $json['message'] ?? 'Unable to grant bKash token.'));
        return ['success' => false, 'error' => $message, 'data' => $json];
    }

    return ['success' => true, 'token' => $token, 'data' => $json];
}

function paymentStartBkash(PDO $db, array $order): array
{
    $cfg = paymentGetBkashConfig();
    if (!$cfg['enabled']) {
        return ['success' => false, 'error' => 'bKash is disabled.'];
    }

    $grant = paymentBkashGrantToken($cfg);
    if (!$grant['success']) {
        return ['success' => false, 'error' => $grant['error'] ?? 'Unable to authorize bKash API.'];
    }

    $payloadArr = [
        'mode' => '0011',
        'payerReference' => (string)($order['shipping_phone'] ?? ''),
        'callbackURL' => paymentAbsoluteUrl('payment_callback.php', ['gateway' => 'bkash']),
        'amount' => number_format((float)$order['total'], 2, '.', ''),
        'currency' => 'BDT',
        'intent' => 'sale',
        'merchantInvoiceNumber' => $order['order_number'],
    ];

    $response = paymentHttpRequest('POST', $cfg['base_url'] . '/create', [
        'Content-Type: application/json',
        'Accept: application/json',
        'authorization: ' . $grant['token'],
        'x-app-key: ' . $cfg['app_key'],
    ], json_encode($payloadArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $json = is_array($response['json']) ? $response['json'] : [];
    $paymentId = trim((string)($json['paymentID'] ?? ''));
    $checkoutUrl = trim((string)($json['bkashURL'] ?? ''));
    $attemptId = paymentCreateAttempt(
        $db,
        (int)$order['id'],
        'bkash',
        $paymentId !== '' ? $paymentId : null,
        $checkoutUrl !== '' ? 'redirected' : 'failed',
        $payloadArr,
        $json
    );

    if ($checkoutUrl === '' || $paymentId === '') {
        if ($attemptId) {
            paymentUpdateAttempt($db, $attemptId, ['status' => 'failed', 'response_payload' => $json]);
        }
        $message = trim((string)($json['statusMessage'] ?? 'Unable to initialize bKash payment.'));
        return ['success' => false, 'error' => $message];
    }

    return [
        'success' => true,
        'redirect_url' => $checkoutUrl,
        'payment_id' => $paymentId,
        'attempt_id' => $attemptId,
    ];
}

function paymentExecuteBkash(string $paymentId): array
{
    $cfg = paymentGetBkashConfig();
    if (!$cfg['enabled']) {
        return ['success' => false, 'error' => 'bKash is disabled.'];
    }

    $grant = paymentBkashGrantToken($cfg);
    if (!$grant['success']) {
        return ['success' => false, 'error' => $grant['error'] ?? 'Unable to authorize bKash API.'];
    }

    $payloadArr = ['paymentID' => $paymentId];
    $response = paymentHttpRequest('POST', $cfg['base_url'] . '/execute', [
        'Content-Type: application/json',
        'Accept: application/json',
        'authorization: ' . $grant['token'],
        'x-app-key: ' . $cfg['app_key'],
    ], json_encode($payloadArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $json = is_array($response['json']) ? $response['json'] : [];
    $statusCode = trim((string)($json['statusCode'] ?? ''));
    $transactionStatus = strtoupper(trim((string)($json['transactionStatus'] ?? '')));
    $isSuccess = $statusCode === '0000' || in_array($transactionStatus, ['COMPLETED', 'SUCCESS'], true);

    if (!$isSuccess) {
        $message = trim((string)($json['statusMessage'] ?? 'bKash execute failed.'));
        return ['success' => false, 'error' => $message, 'data' => $json];
    }

    return ['success' => true, 'data' => $json];
}

function paymentIsMethodEnabled(string $method): bool
{
    $method = strtolower(trim($method));
    if ($method === 'cod') {
        return paymentBoolSetting('payment_cod_enabled', true);
    }
    if ($method === 'sslcommerz') {
        $cfg = paymentGetSslCommerzConfig();
        return $cfg['enabled'] && $cfg['store_id'] !== '' && $cfg['store_passwd'] !== '';
    }
    if ($method === 'bkash') {
        $cfg = paymentGetBkashConfig();
        return $cfg['enabled']
            && $cfg['app_key'] !== ''
            && $cfg['app_secret'] !== ''
            && $cfg['username'] !== ''
            && $cfg['password'] !== '';
    }
    if ($method === 'nagad') {
        return paymentBoolSetting('payment_nagad_enabled', false) && paymentIsMethodEnabled('sslcommerz');
    }
    return false;
}

function paymentDisplayName(string $method): string
{
    $method = strtolower(trim($method));
    if ($method === 'sslcommerz') {
        return 'SSLCOMMERZ';
    }
    if ($method === 'bkash') {
        return 'bKash';
    }
    if ($method === 'nagad') {
        return 'Nagad';
    }
    if ($method === 'cod') {
        return 'Cash on Delivery';
    }
    return ucfirst($method);
}
