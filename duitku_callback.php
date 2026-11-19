<?php
require_once 'koneksi.php';
require_once 'duitku_config.php';

// Duitku mengirimkan data via application/x-www-form-urlencoded POST
$merchantCode    = $_POST['merchantCode'] ?? '';
$amount          = $_POST['amount'] ?? '';
$merchantOrderId = $_POST['merchantOrderId'] ?? '';
$productDetail   = $_POST['productDetail'] ?? '';
$additionalParam = $_POST['additionalParam'] ?? '';
$paymentCode     = $_POST['paymentCode'] ?? '';
$resultCode      = $_POST['resultCode'] ?? ''; // '00' = Sukses, '01' = Gagal
$merchantUserId  = $_POST['merchantUserId'] ?? '';
$reference       = $_POST['reference'] ?? '';
$signature       = $_POST['signature'] ?? '';

if (empty($merchantCode) || empty($amount) || empty($merchantOrderId) || empty($signature)) {
    http_response_code(400);
    echo "Bad Parameter";
    exit;
}

$calcSignature = hash_hmac('sha256', $merchantCode . $amount . $merchantOrderId, DUITKU_API_KEY);

if ($merchantCode !== DUITKU_MERCHANT_CODE || !hash_equals($calcSignature, $signature)) {
    http_response_code(401);
    echo "Invalid Signature";
    exit;
}

$orderIdEsc = mysqli_real_escape_string($conn, $merchantOrderId);
$refEsc     = mysqli_real_escape_string($conn, $reference);

if ($resultCode === '00') {
    // Jika prefix TOPUP-, update tabel tbl_topup
    if (strpos($merchantOrderId, 'TOPUP-') === 0) {
        $id_topup = (int)str_replace('TOPUP-', '', $merchantOrderId);
        mysqli_query($conn, "UPDATE tbl_topup SET status = 'Berhasil', duitku_reference = '$refEsc' WHERE id_topup = $id_topup");
        mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_topup', 'CALLBACK DUITKU', 'Topup #$id_topup Lunas (Ref: $refEsc)')");
    } 
    // Jika prefix TRX-, update tabel tbl_transaksi
    elseif (strpos($merchantOrderId, 'TRX-') === 0) {
        $id_trx = (int)str_replace('TRX-', '', $merchantOrderId);
        $updated = mysqli_query($conn, "UPDATE tbl_transaksi SET status_pembayaran = 'Lunas', ref_pembayaran = '$refEsc' WHERE id_transaksi = $id_trx AND status_pembayaran = 'Pending' AND duitku_reference = '$refEsc'");
        if ($updated && mysqli_affected_rows($conn) === 1) {
            mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_transaksi', 'CALLBACK DUITKU', 'Transaksi #$id_trx Lunas (Ref: $refEsc)')");
        }
    }
    echo "SUCCESS";
} else {
    // Jika pembayaran dibatalkan / kadaluarsa
    if (strpos($merchantOrderId, 'TRX-') === 0) {
        $id_trx = (int)str_replace('TRX-', '', $merchantOrderId);
        mysqli_query($conn, "UPDATE tbl_transaksi SET status_pembayaran = 'Gagal' WHERE id_transaksi = $id_trx AND status_pembayaran = 'Pending'");
    }
    echo "FAILED";
}
?>