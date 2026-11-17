<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once 'functions.php';

$code = isset($_GET['barcode']) ? trim($_GET['barcode']) : (isset($_POST['barcode']) ? trim($_POST['barcode']) : '');

if (empty($code)) {
    echo json_encode([
        'status' => 'error',
        'found'  => false,
        'pesan'  => 'Kode barcode kosong.'
    ]);
    exit;
}

$code_esc = mysqli_real_escape_string($conn, $code);

// 1. Cari berdasarkan Barcode persis
$q = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM tbl_produk p 
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
    WHERE p.barcode = '$code_esc'
    LIMIT 1
");

// 2. Jika tidak ditemukan, cari berdasarkan ID Produk jika berupa angka
if (mysqli_num_rows($q) === 0 && is_numeric($code)) {
    $id_num = (int)$code;
    $q = mysqli_query($conn, "
        SELECT p.*, k.nama_kategori 
        FROM tbl_produk p 
        LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
        WHERE p.id_produk = $id_num
        LIMIT 1
    ");
}

// 3. Jika masih tidak ditemukan, cari berdasarkan pencocokan parsial
if (mysqli_num_rows($q) === 0) {
    $q = mysqli_query($conn, "
        SELECT p.*, k.nama_kategori 
        FROM tbl_produk p 
        LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
        WHERE p.barcode LIKE '%$code_esc%' OR p.nama_produk LIKE '%$code_esc%'
        LIMIT 1
    ");
}

if ($prod = mysqli_fetch_assoc($q)) {
    echo json_encode([
        'status' => 'success',
        'found'  => true,
        'produk' => [
            'id_produk'     => (int)$prod['id_produk'],
            'barcode'       => $prod['barcode'] ?? ('899' . str_pad($prod['id_produk'], 8, '0', STR_PAD_LEFT)),
            'nama_produk'   => $prod['nama_produk'],
            'harga'         => (float)$prod['harga'],
            'harga_format'  => rupiah($prod['harga']),
            'stok'          => (int)$prod['stok'],
            'nama_kategori' => $prod['nama_kategori'] ?? 'Umum'
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'found'  => false,
        'pesan'  => "Produk dengan barcode '$code' tidak ditemukan di database inventori."
    ]);
}
