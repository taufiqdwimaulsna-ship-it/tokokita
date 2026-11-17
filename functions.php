<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Otomatis menyiapkan tabel tambahan untuk fitur Topup, Favorit, dan Voucher Diskon
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_topup (
    id_topup INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT,
    nominal DECIMAL(12,2),
    metode VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Berhasil',
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_favorit (
    id_favorit INT AUTO_INCREMENT PRIMARY KEY,
    id_produk INT UNIQUE,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_voucher (
    id_voucher INT AUTO_INCREMENT PRIMARY KEY,
    kode_voucher VARCHAR(30) UNIQUE,
    persen_diskon DECIMAL(5,2),
    min_belanja DECIMAL(12,2)
)");

// Isi voucher contoh jika kosong
$cek_v = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_voucher"));
if ($cek_v['c'] == 0) {
    mysqli_query($conn, "INSERT INTO tbl_voucher (kode_voucher, persen_diskon, min_belanja) VALUES
        ('HEMAT10', 10, 50000),
        ('PROMO20', 20, 150000),
        ('GAJIAN25', 25, 300000)");
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function cekLogin() {
    if (!isset($_SESSION['login_toko'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Mengecek apakah user yang sedang login adalah Pelanggan
 */
function isPelanggan() {
    if (!isset($_SESSION['role'])) return false;
    $r = strtolower(trim($_SESSION['role']));
    return ($r === 'pelanggan' || $r === 'customer' || $r === 'member');
}

/**
 * Pembatasan Akses: Pelanggan HANYA boleh mengakses Dashboard, Kategori, dan Keranjang
 * Jika mencoba mengakses halaman kasir/admin/laporan, otomatis dialihkan ke index.php
 */
function batasiAksesPelanggan() {
    cekLogin();
    if (isPelanggan()) {
        $_SESSION['pesan_akses'] = "Akses Dibatasi! Akun Pelanggan hanya dapat mengakses halaman Dashboard, Kategori, dan Keranjang.";
        header("Location: index.php");
        exit;
    }
}
?>