<?php
$duitkuMerchantCode = getenv('DUITKU_MERCHANT_CODE');
$duitkuApiKey       = getenv('DUITKU_API_KEY');
$duitkuEnv          = getenv('DUITKU_ENV');

define('DUITKU_MERCHANT_CODE', ($duitkuMerchantCode !== false && $duitkuMerchantCode !== '') ? $duitkuMerchantCode : 'DS38178');
define('DUITKU_API_KEY', ($duitkuApiKey !== false && $duitkuApiKey !== '') ? $duitkuApiKey : '97011abea76c3b289c23d186218c3b6f');
define('DUITKU_ENV', ($duitkuEnv !== false && strtolower($duitkuEnv) === 'production') ? 'production' : 'sandbox');
define('DUITKU_QRIS_MAX_AMOUNT', 10000000);

function validasiBatasNominalDuitku($paymentMethod, $paymentAmount) {
    if (in_array($paymentMethod, ['NQ', 'SP'], true) && (float)$paymentAmount > DUITKU_QRIS_MAX_AMOUNT) {
        return 'Pembayaran QRIS Duitku maksimal Rp 10.000.000 per transaksi. Pilih Virtual Account BCA/BRI atau kurangi jumlah belanja.';
    }

    return '';
}

function duitku_konfigurasi_siap() {
    return !empty(DUITKU_MERCHANT_CODE)
        && DUITKU_MERCHANT_CODE !== 'DS12345'
        && !empty(DUITKU_API_KEY)
        && DUITKU_API_KEY !== 'MASUKKAN_API_KEY_SANDBOX_DISINI';
}

define('DUITKU_API_URL', DUITKU_ENV === 'sandbox'
    ? 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry'
    : 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
);
define('DUITKU_STATUS_API_URL', DUITKU_ENV === 'sandbox'
    ? 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus'
    : 'https://passport.duitku.com/webapi/api/merchant/transactionStatus'
);
define('DUITKU_PAYMENT_METHOD_API_URL', DUITKU_ENV === 'sandbox'
    ? 'https://sandbox.duitku.com/webapi/api/merchant/paymentmethod/getpaymentmethod'
    : 'https://passport.duitku.com/webapi/api/merchant/paymentmethod/getpaymentmethod'
);

define('DUITKU_CALLBACK_URL', getenv('DUITKU_CALLBACK_URL') !== false
    ? getenv('DUITKU_CALLBACK_URL')
    : 'http://localhost/tokokita/duitku_callback.php');

define('DUITKU_RETURN_URL', getenv('DUITKU_RETURN_URL') !== false
    ? getenv('DUITKU_RETURN_URL')
    : 'http://localhost/tokokita/keranjang.php');

function namaMetodeDuitku($paymentMethod, $fallback = '') {
    static $labels = [
        'VA' => 'Maybank Virtual Account',
        'FT' => 'Retail',
        'VC' => 'Kartu Kredit',
        'BT' => 'Permata Virtual Account',
        'B1' => 'CIMB Niaga Virtual Account',
        'A1' => 'ATM Bersama Virtual Account',
        'I1' => 'BNI Virtual Account',
        'OV' => 'OVO',
        'M2' => 'Mandiri Virtual Account',
        'AG' => 'Artha Graha Virtual Account',
        'S1' => 'Sampoerna Virtual Account',
        'SP' => 'ShopeePay QRIS',
        'DN' => 'Indodana PayLater',
        'LA' => 'LinkAja',
        'SA' => 'ShopeePay',
        'DA' => 'DANA',
        'BC' => 'BCA Virtual Account',
        'IR' => 'Indomaret',
        'BR' => 'BRI Virtual Account',
        'NC' => 'BNC Virtual Account',
        'NQ' => 'Nobu QRIS',
        'JP' => 'Jenius Pay',
        'BV' => 'BSI Virtual Account'
    ];

    $paymentMethod = strtoupper(trim((string)$paymentMethod));
    return $labels[$paymentMethod] ?? ($fallback !== '' ? $fallback : $paymentMethod);
}

function grupMetodeDuitku($paymentMethod) {
    $paymentMethod = strtoupper(trim((string)$paymentMethod));
    if (in_array($paymentMethod, ['NQ', 'SP'], true)) {
        return 'QRIS';
    }
    if (in_array($paymentMethod, ['OV', 'DN', 'LA', 'SA', 'DA'], true)) {
        return 'Dompet Digital & PayLater';
    }
    if (in_array($paymentMethod, ['FT', 'IR'], true)) {
        return 'Gerai';
    }
    if ($paymentMethod === 'VC') {
        return 'Kartu Kredit';
    }
    return 'Virtual Account & Transfer Bank';
}

function isMetodeDuitku($paymentMethod) {
    return preg_match('/^[A-Z0-9]{2,3}$/', strtoupper(trim((string)$paymentMethod))) === 1;
}

function ambilMetodePembayaranDuitku($paymentAmount = 10000) {
    if (!duitku_konfigurasi_siap()) {
        return ['status' => false, 'pesan' => 'Konfigurasi Duitku belum siap.', 'methods' => []];
    }

    $amount = max(1, (int)round((float)$paymentAmount));
    $datetime = date('Y-m-d H:i:s');
    $signature = hash_hmac(
        'sha256',
        DUITKU_MERCHANT_CODE . $amount . $datetime,
        DUITKU_API_KEY
    );
    $payload = json_encode([
        'merchantcode' => DUITKU_MERCHANT_CODE,
        'amount' => $amount,
        'datetime' => $datetime,
        'signature' => $signature
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 20
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents(DUITKU_PAYMENT_METHOD_API_URL, false, $context);
    if ($response === false) {
        $streamError = error_get_last();
        return [
            'status' => false,
            'pesan' => 'Gagal memuat metode pembayaran Duitku: ' . ($streamError['message'] ?? 'koneksi gagal'),
            'methods' => []
        ];
    }

    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $match)) {
                $httpCode = (int)$match[1];
                break;
            }
        }
    }

    $result = json_decode($response, true);
    if (
        $httpCode !== 200
        || !is_array($result)
        || (string)($result['responseCode'] ?? '') !== '00'
        || !isset($result['paymentFee'])
        || !is_array($result['paymentFee'])
    ) {
        return [
            'status' => false,
            'pesan' => $result['responseMessage'] ?? $result['Message'] ?? ('HTTP ' . $httpCode . ' saat memuat metode Duitku.'),
            'methods' => []
        ];
    }

    $methods = [];
    foreach ($result['paymentFee'] as $method) {
        $code = strtoupper(trim((string)($method['paymentMethod'] ?? '')));
        $name = trim((string)($method['paymentName'] ?? ''));
        $group = grupMetodeDuitku($code);
        if ($code !== '' && $name !== '' && $group !== 'Dompet Digital & PayLater') {
            $methods[$code] = [
                'code' => $code,
                'name' => namaMetodeDuitku($code, $name),
                'gateway_name' => $name,
                'group' => $group
            ];
        }
    }

    $groupOrder = array_flip([
        'QRIS',
        'Virtual Account & Transfer Bank',
        'Gerai',
        'Kartu Kredit'
    ]);
    uasort($methods, function ($left, $right) use ($groupOrder) {
        $leftGroup = $groupOrder[$left['group']] ?? 99;
        $rightGroup = $groupOrder[$right['group']] ?? 99;
        if ($leftGroup !== $rightGroup) {
            return $leftGroup <=> $rightGroup;
        }
        return strcasecmp($left['name'], $right['name']);
    });

    return ['status' => true, 'pesan' => '', 'methods' => $methods];
}

function buatTransaksiDuitku($merchantOrderId, $paymentAmount, $paymentMethod, $productDetails, $customerName, $customerEmail = 'pelanggan@tokokita.local') {
    $pesanBatasNominal = validasiBatasNominalDuitku($paymentMethod, $paymentAmount);
    if ($pesanBatasNominal !== '') {
        return ['status' => false, 'pesan' => $pesanBatasNominal];
    }

    $merchantCode = DUITKU_MERCHANT_CODE;
    $apiKey       = DUITKU_API_KEY;
    $amountInt    = (int)round((float)$paymentAmount);

    // Duitku Inquiry v2 signs merchantCode + merchantOrderId + paymentAmount with HMAC-SHA256.
    $signature = hash_hmac('sha256', $merchantCode . $merchantOrderId . $amountInt, $apiKey);

    $params = [
        'merchantCode'     => $merchantCode,
        'paymentAmount'    => $amountInt,
        'paymentMethod'    => $paymentMethod,
        'merchantOrderId'  => (string)$merchantOrderId,
        'productDetails'   => $productDetails,
        'customerVaName'   => $customerName,
        'email'            => $customerEmail,
        'callbackUrl'      => DUITKU_CALLBACK_URL,
        'returnUrl'        => DUITKU_RETURN_URL,
        'signature'        => $signature,
        'expiryPeriod'     => 60
    ];

    $payload = json_encode($params);

    $httpCode = 0;
    $response = '';

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, DUITKU_API_URL);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['status' => false, 'pesan' => 'cURL Error: ' . $curlErr];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $response = @file_get_contents(DUITKU_API_URL, false, $context);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $match)) {
                    $httpCode = (int)$match[1];
                    break;
                }
            }
        }
        if ($response === false) {
            $streamError = error_get_last();
            return ['status' => false, 'pesan' => 'HTTP Error: ' . ($streamError['message'] ?? 'Permintaan ke Duitku gagal')];
        }
    }

    $result = json_decode($response, true);
    if ($httpCode === 200 && isset($result['statusCode']) && (string)$result['statusCode'] === '00'
        && !empty($result['reference']) && !empty($result['paymentUrl'])) {
        return [
            'status'      => true,
            'reference'   => $result['reference'],
            'paymentUrl'  => $result['paymentUrl'],
            'vaNumber'    => $result['vaNumber'] ?? '',
            'qrString'    => $result['qrString'] ?? ''
        ];
    }

    return [
        'status' => false,
        'pesan'  => $result['Message'] ?? $result['statusMessage'] ?? ('HTTP ' . $httpCode . ' - ' . $response)
    ];
}

function cekStatusTransaksiDuitku($merchantOrderId) {
    $params = [
        'merchantCode' => DUITKU_MERCHANT_CODE,
        'merchantOrderId' => (string)$merchantOrderId,
        'signature' => hash_hmac('sha256', DUITKU_MERCHANT_CODE . $merchantOrderId, DUITKU_API_KEY)
    ];
    $payload = json_encode($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 20
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents(DUITKU_STATUS_API_URL, false, $context);
    if ($response === false) {
        $streamError = error_get_last();
        return ['status' => false, 'pesan' => 'Gagal memeriksa status Duitku: ' . ($streamError['message'] ?? 'koneksi gagal')];
    }

    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $match)) {
                $httpCode = (int)$match[1];
                break;
            }
        }
    }

    $result = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($result) || !isset($result['statusCode'])) {
        return [
            'status' => false,
            'pesan' => $result['statusMessage'] ?? $result['Message'] ?? ('HTTP ' . $httpCode . ' saat memeriksa status Duitku.')
        ];
    }

    $result['status'] = true;
    return $result;
}
?>