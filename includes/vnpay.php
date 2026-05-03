<?php

date_default_timezone_set('Asia/Ho_Chi_Minh');

function vnpayIsConfigured(): bool
{
    return defined('VNPAY_ENABLED')
        && VNPAY_ENABLED
        && defined('VNPAY_URL')
        && defined('VNPAY_TMN_CODE')
        && defined('VNPAY_HASH_SECRET')
        && trim(VNPAY_TMN_CODE) !== ''
        && trim(VNPAY_HASH_SECRET) !== '';
}

function vnpayClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function vnpayBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

    return $scheme . '://' . $host;
}

function vnpayBuildPaymentUrl(array $order, string $returnUrl): string
{
    if (!vnpayIsConfigured()) {
        throw new RuntimeException('VNPAY is not configured.');
    }

    $amount = (int) round((float) ($order['amount'] ?? 0));
    $orderCode = trim((string) ($order['order_code'] ?? ''));

    if ($amount <= 0) {
        throw new RuntimeException('Invalid payment amount.');
    }

    if ($orderCode === '') {
        throw new RuntimeException('Invalid order code.');
    }

    $inputData = [
        'vnp_Version' => '2.1.0',
        'vnp_TmnCode' => trim(VNPAY_TMN_CODE),
        'vnp_Amount' => $amount * 100,
        'vnp_Command' => 'pay',
        'vnp_CreateDate' => date('YmdHis'),
        'vnp_CurrCode' => 'VND',
        'vnp_IpAddr' => vnpayClientIp(),
        'vnp_Locale' => 'vn',
        'vnp_OrderInfo' => 'Thanh toán đơn hàng ' . $orderCode,
        'vnp_OrderType' => 'billpayment',
        'vnp_ReturnUrl' => $returnUrl,
        'vnp_TxnRef' => $orderCode,
        'vnp_ExpireDate' => date('YmdHis', strtotime('+60 minutes')),
    ];

    ksort($inputData);

    $query = '';
    $hashData = '';
    $i = 0;

    foreach ($inputData as $key => $value) {
        if ($i === 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode((string) $value);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode((string) $value);
            $i = 1;
        }

        $query .= urlencode($key) . '=' . urlencode((string) $value) . '&';
    }

    $secureHash = hash_hmac('sha512', $hashData, trim(VNPAY_HASH_SECRET));

    return rtrim(VNPAY_URL, '?') . '?' . $query . 'vnp_SecureHash=' . $secureHash;
}

function vnpayVerifyReturn(array $data): bool
{
    if (!vnpayIsConfigured() || empty($data['vnp_SecureHash'])) {
        return false;
    }

    $secureHash = (string) $data['vnp_SecureHash'];
    $inputData = [];

    foreach ($data as $key => $value) {
        if (substr($key, 0, 4) === 'vnp_' && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
            $inputData[$key] = $value;
        }
    }

    ksort($inputData);

    $hashData = '';
    $i = 0;

    foreach ($inputData as $key => $value) {
        if ($i === 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode((string) $value);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode((string) $value);
            $i = 1;
        }
    }

    $calculatedHash = hash_hmac('sha512', $hashData, trim(VNPAY_HASH_SECRET));

    return hash_equals($calculatedHash, $secureHash);
}
