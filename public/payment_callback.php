<?php
/**
 * Rosabella - Payment Callback Handler
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

$db = getDB();

function redirectPaymentResult(string $status, ?string $orderNumber, string $message = ''): void
{
    $query = ['status' => $status];
    if ($orderNumber) {
        $query['order'] = $orderNumber;
    }
    if ($message !== '') {
        $query['message'] = $message;
    }
    header('Location: ' . paymentAbsoluteUrl('payment_result', $query));
    exit;
}

$gateway = strtolower(trim((string)($_GET['gateway'] ?? '')));
if ($gateway === 'sslcommerz') {
    $action = strtolower(trim((string)($_GET['action'] ?? '')));
    $tranId = trim((string)($_POST['tran_id'] ?? $_GET['tran_id'] ?? ''));
    if ($tranId === '') {
        redirectPaymentResult('failed', null, 'Missing transaction reference.');
    }

    $order = paymentGetOrderByNumber($db, $tranId);
    if (!$order) {
        redirectPaymentResult('failed', null, 'Order not found for payment callback.');
    }

    if (($order['payment_status'] ?? '') === 'paid') {
        paymentClearUserCart($db, (int)$order['user_id']);
        redirectPaymentResult('success', $order['order_number'], 'Payment already confirmed.');
    }

    if ($action === 'success') {
        $valId = trim((string)($_POST['val_id'] ?? $_GET['val_id'] ?? ''));
        $validation = paymentValidateSslCommerz($valId);
        if (empty($validation['success'])) {
            $error = trim((string)($validation['error'] ?? 'SSLCOMMERZ validation failed.'));
            paymentMarkOrderFailed($db, (int)$order['id'], 'sslcommerz', 'Validation failed: ' . $error);
            paymentCreateAttempt(
                $db,
                (int)$order['id'],
                'sslcommerz',
                $valId !== '' ? $valId : null,
                'failed',
                $_POST,
                is_array($validation['data'] ?? null) ? $validation['data'] : ['error' => $error]
            );
            redirectPaymentResult('failed', $order['order_number'], $error);
        }

        $validated = $validation['data'] ?? [];
        $validatedTranId = trim((string)($validated['tran_id'] ?? ''));
        $validatedAmount = (float)($validated['amount'] ?? 0);
        $orderTotal = (float)$order['total'];
        $amountDelta = abs($validatedAmount - $orderTotal);

        if ($validatedTranId !== $order['order_number'] || $amountDelta > 1) {
            paymentMarkOrderFailed($db, (int)$order['id'], 'sslcommerz', 'Validation mismatch on transaction reference or amount.');
            paymentCreateAttempt(
                $db,
                (int)$order['id'],
                'sslcommerz',
                $valId !== '' ? $valId : null,
                'failed',
                $_POST,
                $validated
            );
            redirectPaymentResult('failed', $order['order_number'], 'Payment validation mismatch. Please contact support.');
        }

        $gatewayTxnId = trim((string)($validated['bank_tran_id'] ?? $validated['tran_id'] ?? ''));
        paymentMarkOrderPaid(
            $db,
            (int)$order['id'],
            'sslcommerz',
            $gatewayTxnId,
            'SSLCOMMERZ payment validated successfully.'
        );
        paymentCreateAttempt(
            $db,
            (int)$order['id'],
            'sslcommerz',
            $valId !== '' ? $valId : null,
            'paid',
            $_POST,
            $validated
        );
        paymentClearUserCart($db, (int)$order['user_id']);
        redirectPaymentResult('success', $order['order_number'], 'Payment successful.');
    }

    $reason = trim((string)($_POST['failedreason'] ?? $_GET['failedreason'] ?? 'Payment was not completed.'));
    $isCancel = $action === 'cancel';
    $statusLabel = $isCancel ? 'cancelled' : 'failed';
    paymentMarkOrderFailed(
        $db,
        (int)$order['id'],
        'sslcommerz',
        'SSLCOMMERZ callback: ' . $statusLabel . '. Reason: ' . $reason,
        $isCancel
    );
    paymentCreateAttempt(
        $db,
        (int)$order['id'],
        'sslcommerz',
        trim((string)($_POST['val_id'] ?? $_GET['val_id'] ?? '')),
        $statusLabel,
        $_POST,
        ['reason' => $reason]
    );
    redirectPaymentResult($statusLabel, $order['order_number'], $reason);
}

if ($gateway === 'bkash') {
    $paymentId = trim((string)($_GET['paymentID'] ?? $_POST['paymentID'] ?? ''));
    $status = strtolower(trim((string)($_GET['status'] ?? $_POST['status'] ?? '')));

    if ($paymentId === '') {
        redirectPaymentResult('failed', null, 'Missing bKash payment reference.');
    }

    $attempt = paymentFindAttemptByGatewayPaymentId($db, 'bkash', $paymentId);
    if (!$attempt) {
        redirectPaymentResult('failed', null, 'Unable to locate this bKash payment attempt.');
    }

    $order = paymentGetOrderById($db, (int)$attempt['order_id']);
    if (!$order) {
        redirectPaymentResult('failed', null, 'Order not found for this bKash payment.');
    }

    if (($order['payment_status'] ?? '') === 'paid') {
        paymentUpdateAttempt($db, (int)$attempt['id'], ['status' => 'paid']);
        paymentClearUserCart($db, (int)$order['user_id']);
        redirectPaymentResult('success', $order['order_number'], 'Payment already confirmed.');
    }

    if ($status === 'success') {
        $execute = paymentExecuteBkash($paymentId);
        if (!empty($execute['success'])) {
            $data = $execute['data'] ?? [];
            $trxId = trim((string)($data['trxID'] ?? $data['paymentID'] ?? $paymentId));
            paymentMarkOrderPaid(
                $db,
                (int)$order['id'],
                'bkash',
                $trxId,
                'bKash payment executed successfully.'
            );
            paymentUpdateAttempt($db, (int)$attempt['id'], [
                'gateway_transaction_id' => $trxId,
                'status' => 'paid',
                'response_payload' => $data,
            ]);
            paymentClearUserCart($db, (int)$order['user_id']);
            redirectPaymentResult('success', $order['order_number'], 'bKash payment successful.');
        }

        $error = trim((string)($execute['error'] ?? 'bKash payment execution failed.'));
        paymentMarkOrderFailed($db, (int)$order['id'], 'bkash', 'Execute failed: ' . $error);
        paymentUpdateAttempt($db, (int)$attempt['id'], [
            'status' => 'failed',
            'response_payload' => is_array($execute['data'] ?? null) ? $execute['data'] : ['error' => $error],
        ]);
        redirectPaymentResult('failed', $order['order_number'], $error);
    }

    if (in_array($status, ['cancel', 'cancelled'], true)) {
        paymentMarkOrderFailed($db, (int)$order['id'], 'bkash', 'bKash checkout cancelled by customer.', true);
        paymentUpdateAttempt($db, (int)$attempt['id'], ['status' => 'cancelled', 'response_payload' => $_GET]);
        redirectPaymentResult('cancelled', $order['order_number'], 'Payment cancelled.');
    }

    paymentMarkOrderFailed($db, (int)$order['id'], 'bkash', 'bKash checkout failed. Status: ' . $status);
    paymentUpdateAttempt($db, (int)$attempt['id'], ['status' => 'failed', 'response_payload' => $_GET]);
    redirectPaymentResult('failed', $order['order_number'], 'Payment failed. Please try again.');
}

redirectPaymentResult('failed', null, 'Unsupported payment gateway callback.');

