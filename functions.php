<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

$cek_tabel_users = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_users'");
if ($cek_tabel_users && mysqli_num_rows($cek_tabel_users) > 0) {
    $cek_id_pelanggan_user = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'id_pelanggan'");
    if ($cek_id_pelanggan_user && mysqli_num_rows($cek_id_pelanggan_user) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE tbl_users ADD COLUMN id_pelanggan INT NULL UNIQUE")) {
            error_log('Gagal menambahkan relasi pelanggan ke tbl_users: ' . mysqli_error($conn));
        }
    }
}

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

// Otomatis memastikan kolom barcode tersedia di tbl_produk untuk fitur Scanner Barcode Produk
$cek_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_produk LIKE 'barcode'");
if (mysqli_num_rows($cek_col) == 0) {
    @mysqli_query($conn, "ALTER TABLE tbl_produk ADD COLUMN barcode VARCHAR(50) DEFAULT NULL AFTER id_kategori");
    @mysqli_query($conn, "CREATE UNIQUE INDEX idx_barcode ON tbl_produk (barcode)");
}

$cek_foto_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_produk LIKE 'foto_url'");
if ($cek_foto_col && mysqli_num_rows($cek_foto_col) === 0) {
    @mysqli_query($conn, "ALTER TABLE tbl_produk ADD COLUMN foto_url VARCHAR(500) NULL AFTER barcode");
}

// Kolom dukungan Payment Gateway Duitku
$kolom_transaksi = [
    'metode_pembayaran' => 'VARCHAR(50) NULL',
    'status_pembayaran' => "VARCHAR(20) NULL DEFAULT 'Pending'",
    'waktu_transaksi' => 'DATETIME NULL DEFAULT NULL',
    'ref_pembayaran' => 'VARCHAR(100) NULL',
    'alamat_pengiriman' => 'TEXT NULL',
    'no_telepon' => 'VARCHAR(30) NULL',
    'catatan_pengiriman' => 'TEXT NULL',
    'duitku_reference' => 'VARCHAR(100) NULL',
    'duitku_url' => 'TEXT NULL'
];
foreach ($kolom_transaksi as $namaKolom => $definisiKolom) {
    $cek = mysqli_query($conn, "SHOW COLUMNS FROM tbl_transaksi LIKE '" . mysqli_real_escape_string($conn, $namaKolom) . "'");
    if ($cek && mysqli_num_rows($cek) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_transaksi ADD COLUMN `$namaKolom` $definisiKolom");
    }
}

// Otomatis pastikan Trigger Validasi Stok sebelum transaksi (mencegah stok minus / checkout melebihi stok)
@mysqli_query($conn, "
CREATE TRIGGER IF NOT EXISTS before_insert_detail_transaksi
BEFORE INSERT ON tbl_detail_transaksi
FOR EACH ROW
BEGIN
    DECLARE v_stok INT DEFAULT 0;
    IF NEW.id_produk IS NOT NULL THEN
        SELECT stok INTO v_stok FROM tbl_produk WHERE id_produk = NEW.id_produk;
        IF v_stok < NEW.jumlah THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Stok barang tidak mencukupi untuk melakukan transaksi!';
        END IF;
    END IF;
END;
");

function getFotoProduk($nama, $foto_url = '') {
    if (!empty($foto_url)) return $foto_url;
    $n = strtolower($nama);
    if (strpos($n, 'beras') !== false) return 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'minyak') !== false) return 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'kopi') !== false) return 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'buku') !== false) return 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'aqua') !== false) return 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'sepatu') !== false || strpos($n, 'shoes') !== false) return 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'motorola') !== false || strpos($n, 'hp') !== false) return 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'audio') !== false || strpos($n, 'keyboard') !== false) return 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'kulkas') !== false) return 'https://images.unsplash.com/photo-1584992236310-6edddc08acff?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'grinder') !== false) return 'https://images.unsplash.com/photo-1504148455328-c376907d081c?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'gps') !== false) return 'https://images.unsplash.com/photo-1508739773434-c26b3d09e071?w=500&auto=format&fit=crop&q=80';
    if (strpos($n, 'jaket') !== false) return 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&auto=format&fit=crop&q=80';
    return 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500&auto=format&fit=crop&q=80';
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
 * Mengecek apakah user yang sedang login adalah Administrator
 */
function isAdmin() {
    if (!isset($_SESSION['role'])) return false;
    $r = strtolower(trim($_SESSION['role']));
    return ($r === 'super admin' || $r === 'admin' || $r === 'administrator');
}

/**
 * Mengecek apakah user yang sedang login adalah Kasir
 */
function isKasir() {
    if (!isset($_SESSION['role'])) return false;
    $r = strtolower(trim($_SESSION['role']));
    return ($r === 'kasir' || $r === 'kasir pos');
}

/**
 * Mengecek apakah user yang sedang login adalah Pelanggan
 */
function isPelanggan() {
    if (!isset($_SESSION['role'])) return false;
    $r = strtolower(trim($_SESSION['role']));
    return ($r === 'pelanggan' || $r === 'customer' || $r === 'member');
}

function getIdPelangganLogin() {
    global $conn;

    if (!isPelanggan() || empty($_SESSION['id_user'])) {
        return 0;
    }

    $id_user = (int)$_SESSION['id_user'];
    $q_user = mysqli_query($conn, "SELECT id_pelanggan FROM tbl_users WHERE id_user = $id_user LIMIT 1");
    if (!$q_user) {
        error_log('Gagal membaca relasi pelanggan akun: ' . mysqli_error($conn));
        return 0;
    }

    $user = mysqli_fetch_assoc($q_user);
    if (!$user) {
        return 0;
    }
    if ((int)($user['id_pelanggan'] ?? 0) > 0) {
        return (int)$user['id_pelanggan'];
    }

    $q_match = mysqli_query($conn, "
        SELECT p.id_pelanggan
        FROM tbl_users u
        JOIN tbl_pelanggan p ON BINARY p.nama_pelanggan = BINARY u.nama_lengkap
        WHERE u.id_user = $id_user
          AND LOWER(TRIM(u.role)) IN ('pelanggan', 'customer', 'member')
          AND (SELECT COUNT(*) FROM tbl_pelanggan p2 WHERE BINARY p2.nama_pelanggan = BINARY u.nama_lengkap) = 1
          AND (SELECT COUNT(*) FROM tbl_users u2
               WHERE BINARY u2.nama_lengkap = BINARY u.nama_lengkap
                 AND LOWER(TRIM(u2.role)) IN ('pelanggan', 'customer', 'member')) = 1
          AND NOT EXISTS (
              SELECT 1 FROM tbl_users u3
              WHERE u3.id_pelanggan = p.id_pelanggan AND u3.id_user <> u.id_user
          )
        LIMIT 1
    ");
    if (!$q_match) {
        error_log('Gagal mencocokkan akun dengan data pelanggan: ' . mysqli_error($conn));
        return 0;
    }

    $match = mysqli_fetch_assoc($q_match);
    if (!$match) {
        return 0;
    }

    $id_pelanggan = (int)$match['id_pelanggan'];
    if (!mysqli_query($conn, "UPDATE tbl_users SET id_pelanggan = $id_pelanggan WHERE id_user = $id_user AND id_pelanggan IS NULL")) {
        error_log('Gagal menyimpan relasi akun pelanggan: ' . mysqli_error($conn));
        return 0;
    }

    $q_confirm = mysqli_query($conn, "SELECT id_pelanggan FROM tbl_users WHERE id_user = $id_user LIMIT 1");
    $confirmed = $q_confirm ? mysqli_fetch_assoc($q_confirm) : null;
    return (int)($confirmed['id_pelanggan'] ?? 0) === $id_pelanggan ? $id_pelanggan : 0;
}

function statusPembayaranTampilan($status) {
    $status = trim((string)$status);
    $status_normal = strtolower($status);

    if (in_array($status_normal, ['pending', 'menunggu pembayaran'], true)) {
        return ['label' => 'Menunggu Pembayaran', 'class' => 'bg-amber-100 text-amber-800'];
    }
    if (in_array($status_normal, ['gagal', 'batal', 'dibatalkan', 'cancelled', 'canceled', 'expired', 'kadaluarsa'], true)) {
        return ['label' => 'Transaksi Batal', 'class' => 'bg-rose-100 text-rose-800'];
    }
    if (in_array($status_normal, ['lunas', 'berhasil', 'paid', 'success'], true)) {
        return ['label' => 'Selesai Dibayar', 'class' => 'bg-emerald-100 text-emerald-800'];
    }

    return [
        'label' => $status !== '' ? $status : 'Status Belum Tersedia',
        'class' => 'bg-slate-100 text-slate-700'
    ];
}

/**
 * Pembatasan Akses: Pelanggan tidak boleh mengakses halaman khusus internal
 */
function batasiAksesPelanggan() {
    cekLogin();
    if (isPelanggan()) {
        $_SESSION['pesan_akses'] = "Akses Dibatasi! Halaman ini khusus untuk Administrator dan Kasir POS.";
        header("Location: index.php");
        exit;
    }
}
?>