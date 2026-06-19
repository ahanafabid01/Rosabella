<?php
/**
 * KARTLY - Payment IPN Handler
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

$gateway = strtolower(trim((string)($_GET['gateway'] ?? '')));
if ($gateway !== 'sslcommerz') {
    http_response_code(400);
    echo 'Unsupported gateway';
    exit;
}

$db = getDB();
$tranId = trim((string)($_POST['tran_id'] ?? ''));
$valId = trim((string)($_POST['val_id'] ?? ''));

if ($tranId === '' || $valId === '') {
    http_response_code(422);
    echo 'Missing parameters';
    exit;
}

$order = paymentGetOrderByNumber($db, $tranId);
if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

if (($order['payment_status'] ?? '') === 'paid') {
    echo 'OK';
    exit;
}

$validation = paymentValidateSslCommerz($valId);
if (empty($validation['success'])) {
    paymentMarkOrderFailed(
        $db,
        (int)$order['id'],
        'sslcommerz',
        'IPN validation failed: ' . trim((string)($validation['error'] ?? 'Unknown error.'))
    );
    paymentCreateAttempt(
        $db,
        (int)$order['id'],
        'sslcommerz',
        $valId,
        'failed',
        $_POST,
        is_array($validation['data'] ?? null) ? $validation['data'] : ['error' => ($validation['error'] ?? 'Validation failed')]
    );
    http_response_code(422);
    echo 'Validation failed';
    exit;
}

$validated = $validation['data'] ?? [];
$validatedTranId = trim((string)($validated['tran_id'] ?? ''));
$validatedAmount = (float)($validated['amount'] ?? 0);
$orderTotal = (float)$order['total'];
$amountDelta = abs($validatedAmount - $orderTotal);

if ($validatedTranId !== $order['order_number'] || $amountDelta > 1) {
    paymentMarkOrderFailed($db, (int)$order['id'], 'sslcommerz', 'IPN validation mismatch on transaction reference or amount.');
    paymentCreateAttempt($db, (int)$order['id'], 'sslcommerz', $valId, 'failed', $_POST, $validated);
    http_response_code(422);
    echo 'Validation mismatch';
    exit;
}

$gatewayTxnId = trim((string)($validated['bank_tran_id'] ?? $validated['tran_id'] ?? ''));
paymentMarkOrderPaid(
    $db,
    (int)$order['id'],
    'sslcommerz',
    $gatewayTxnId,
    'SSLCOMMERZ IPN validated successfully.'
);
paymentCreateAttempt($db, (int)$order['id'], 'sslcommerz', $valId, 'paid', $_POST, $validated);
paymentClearUserCart($db, (int)$order['user_id']);

echo 'OK';

