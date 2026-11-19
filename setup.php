<?php
/**
 * TokoKita - Database Setup & Auto-Installer
 * File: setup.php
 * Deskripsi: Otomatisasi inisialisasi database db_tokokita, tabel, view,
 * stored procedure, stored function, trigger, dan sample data lengkap.
 */

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_tokokita";

$logs = [];
$status_success = true;

function logStatus(&$logs, $pesan, $tipe = 'info') {
    $logs[] = ['pesan' => $pesan, 'tipe' => $tipe, 'waktu' => date('H:i:s')];
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'install' || $action === 'reset') {
    // 1. Koneksi awal ke server MySQL (tanpa memilih database)
    $koneksi_server = @mysqli_connect($host, $user, $pass);
    if (!$koneksi_server) {
        logStatus($logs, "Gagal terhubung ke MySQL Server: " . mysqli_connect_error(), 'error');
        $status_success = false;
    } else {
        logStatus($logs, "Berhasil terhubung ke server MySQL ($host)", 'success');

        // Jika reset, drop database lama
        if ($action === 'reset') {
            if (mysqli_query($koneksi_server, "DROP DATABASE IF EXISTS `$db`")) {
                logStatus($logs, "Database '$db' lama berhasil di-reset / dihapus.", 'warning');
            }
        }

        // 2. Buat database jika belum ada
        $sql_createdb = "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (mysqli_query($koneksi_server, $sql_createdb)) {
            logStatus($logs, "Database '$db' siap digunakan.", 'success');
        } else {
            logStatus($logs, "Gagal membuat database '$db': " . mysqli_error($koneksi_server), 'error');
            $status_success = false;
        }

        // 3. Hubungkan ke database db_tokokita
        $conn = @mysqli_connect($host, $user, $pass, $db);
        if (!$conn) {
            logStatus($logs, "Gagal memilih database '$db': " . mysqli_connect_error(), 'error');
            $status_success = false;
        } else {
            mysqli_set_charset($conn, "utf8mb4");

            // 4. Buat Tabel-tabel Dasar
            $tables = [
                'tbl_users' => "CREATE TABLE IF NOT EXISTS tbl_users (
                    id_user INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    nama_lengkap VARCHAR(100) NOT NULL,
                    role VARCHAR(30) DEFAULT 'Kasir POS',
                    id_pelanggan INT NULL UNIQUE
                )",

                'tbl_kategori' => "CREATE TABLE IF NOT EXISTS tbl_kategori (
                    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
                    nama_kategori VARCHAR(100) NOT NULL
                )",

                'tbl_produk' => "CREATE TABLE IF NOT EXISTS tbl_produk (
                    id_produk INT AUTO_INCREMENT PRIMARY KEY,
                    id_kategori INT,
                    foto_url VARCHAR(500) NULL,
                    nama_produk VARCHAR(100) NOT NULL,
                    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
                    stok INT NOT NULL DEFAULT 0,
                    INDEX(id_kategori)
                )",

                'tbl_pelanggan' => "CREATE TABLE IF NOT EXISTS tbl_pelanggan (
                    id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
                    nama_pelanggan VARCHAR(100) NOT NULL,
                    kota VARCHAR(50) NOT NULL,
                    INDEX(kota)
                )",

                'tbl_transaksi' => "CREATE TABLE IF NOT EXISTS tbl_transaksi (
                    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
                    tanggal DATE NOT NULL,
                    waktu_transaksi DATETIME NULL DEFAULT NULL,
                    id_pelanggan INT,
                    total_belanja DECIMAL(12,2) NOT NULL DEFAULT 0,
                    INDEX(tanggal),
                    INDEX(id_pelanggan)
                )",

                'tbl_detail_transaksi' => "CREATE TABLE IF NOT EXISTS tbl_detail_transaksi (
                    id_detail INT AUTO_INCREMENT PRIMARY KEY,
                    id_transaksi INT NOT NULL,
                    id_produk INT,
                    nama_produk VARCHAR(100) NOT NULL,
                    jumlah INT NOT NULL DEFAULT 1,
                    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
                    INDEX(id_transaksi),
                    INDEX(id_produk)
                )",

                'tbl_cabang' => "CREATE TABLE IF NOT EXISTS tbl_cabang (
                    id_cabang INT AUTO_INCREMENT PRIMARY KEY,
                    nama_cabang VARCHAR(100) NOT NULL,
                    kota VARCHAR(50) NOT NULL
                )",

                'tbl_topup' => "CREATE TABLE IF NOT EXISTS tbl_topup (
                    id_topup INT AUTO_INCREMENT PRIMARY KEY,
                    id_pelanggan INT NOT NULL,
                    nominal DECIMAL(12,2) NOT NULL,
                    metode VARCHAR(50) NOT NULL DEFAULT 'QRIS',
                    status VARCHAR(20) DEFAULT 'Berhasil',
                    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(id_pelanggan)
                )",

                'tbl_voucher' => "CREATE TABLE IF NOT EXISTS tbl_voucher (
                    id_voucher INT AUTO_INCREMENT PRIMARY KEY,
                    kode_voucher VARCHAR(30) UNIQUE NOT NULL,
                    persen_diskon DECIMAL(5,2) NOT NULL,
                    min_belanja DECIMAL(12,2) NOT NULL DEFAULT 0
                )",

                'tbl_favorit' => "CREATE TABLE IF NOT EXISTS tbl_favorit (
                    id_favorit INT AUTO_INCREMENT PRIMARY KEY,
                    id_produk INT UNIQUE NOT NULL,
                    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",

                'tbl_log' => "CREATE TABLE IF NOT EXISTS tbl_log (
                    id_log INT AUTO_INCREMENT PRIMARY KEY,
                    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    tabel_terkait VARCHAR(50),
                    aksi VARCHAR(50),
                    keterangan TEXT
                )",

                'log_pelanggan' => "CREATE TABLE IF NOT EXISTS log_pelanggan (
                    id_log INT AUTO_INCREMENT PRIMARY KEY,
                    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    aktivitas VARCHAR(50),
                    id_pelanggan INT
                )",

                'log_perubahan_pelanggan' => "CREATE TABLE IF NOT EXISTS log_perubahan_pelanggan (
                    id_log INT AUTO_INCREMENT PRIMARY KEY,
                    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    id_pelanggan INT,
                    nama_lama VARCHAR(100),
                    nama_baru VARCHAR(100)
                )"
            ];

            foreach ($tables as $tName => $tSql) {
                if (mysqli_query($conn, $tSql)) {
                    logStatus($logs, "Tabel '$tName' siap.", 'success');
                } else {
                    logStatus($logs, "Gagal membuat tabel '$tName': " . mysqli_error($conn), 'error');
                    $status_success = false;
                }
            }

            // 5. Buat Stored Functions
            $functions = [
                'HitungTotal' => [
                    'drop' => "DROP FUNCTION IF EXISTS HitungTotal",
                    'sql'  => "CREATE FUNCTION HitungTotal(harga DECIMAL(12,2), jumlah INT)
                               RETURNS DECIMAL(12,2)
                               DETERMINISTIC
                               NO SQL
                               BEGIN
                                   RETURN harga * jumlah;
                               END"
                ],
                'HitungDiskon' => [
                    'drop' => "DROP FUNCTION IF EXISTS HitungDiskon",
                    'sql'  => "CREATE FUNCTION HitungDiskon(harga DECIMAL(12,2), diskon DECIMAL(5,2))
                               RETURNS DECIMAL(12,2)
                               DETERMINISTIC
                               NO SQL
                               BEGIN
                                   RETURN harga * (diskon / 100);
                               END"
                ],
                'HitungPajak' => [
                    'drop' => "DROP FUNCTION IF EXISTS HitungPajak",
                    'sql'  => "CREATE FUNCTION HitungPajak(harga DECIMAL(12,2))
                               RETURNS DECIMAL(12,2)
                               DETERMINISTIC
                               NO SQL
                               BEGIN
                                   RETURN harga * 0.11;
                               END"
                ],
                'KategoriBelanja' => [
                    'drop' => "DROP FUNCTION IF EXISTS KategoriBelanja",
                    'sql'  => "CREATE FUNCTION KategoriBelanja(total DECIMAL(12,2))
                               RETURNS VARCHAR(30) CHARSET utf8mb4
                               DETERMINISTIC
                               NO SQL
                               BEGIN
                                   RETURN CASE
                                       WHEN total >= 1000000 THEN 'Pelanggan Gold'
                                       WHEN total >= 500000 THEN 'Pelanggan Silver'
                                       ELSE 'Pelanggan Reguler'
                                   END;
                               END"
                ],
                'KonversiNilai' => [
                    'drop' => "DROP FUNCTION IF EXISTS KonversiNilai",
                    'sql'  => "CREATE FUNCTION KonversiNilai(nilai INT)
                               RETURNS VARCHAR(10) CHARSET utf8mb4
                               DETERMINISTIC
                               NO SQL
                               BEGIN
                                   RETURN CASE
                                       WHEN nilai >= 85 THEN 'A'
                                       WHEN nilai >= 70 THEN 'B'
                                       WHEN nilai >= 55 THEN 'C'
                                       ELSE 'D'
                                   END;
                               END"
                ]
            ];

            foreach ($functions as $fName => $fDef) {
                mysqli_query($conn, $fDef['drop']);
                if (mysqli_query($conn, $fDef['sql'])) {
                    logStatus($logs, "Stored Function '$fName' siap.", 'success');
                } else {
                    logStatus($logs, "Gagal membuat function '$fName': " . mysqli_error($conn), 'error');
                    $status_success = false;
                }
            }

            // 6. Buat Views
            $views = [
                'view_pelanggan' => "CREATE OR REPLACE VIEW view_pelanggan AS
                    SELECT id_pelanggan, nama_pelanggan, kota FROM tbl_pelanggan",

                'view_total_belanja' => "CREATE OR REPLACE VIEW view_total_belanja AS
                    SELECT p.id_pelanggan, p.nama_pelanggan,
                           COUNT(t.id_transaksi) AS total_transaksi,
                           COALESCE(SUM(t.total_belanja), 0) AS total_belanja
                    FROM tbl_pelanggan p
                    LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
                    GROUP BY p.id_pelanggan, p.nama_pelanggan",

                'view_transaksi_pelanggan' => "CREATE OR REPLACE VIEW view_transaksi_pelanggan AS
                    SELECT t.id_transaksi, t.tanggal, t.id_pelanggan, p.nama_pelanggan, p.kota, t.total_belanja
                    FROM tbl_transaksi t
                    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan"
            ];

            foreach ($views as $vName => $vSql) {
                if (mysqli_query($conn, $vSql)) {
                    logStatus($logs, "Database View '$vName' siap.", 'success');
                } else {
                    logStatus($logs, "Gagal membuat view '$vName': " . mysqli_error($conn), 'error');
                    $status_success = false;
                }
            }

            // 7. Buat Stored Procedures
            $procedures = [
                'TambahPelanggan' => [
                    'drop' => "DROP PROCEDURE IF EXISTS TambahPelanggan",
                    'sql'  => "CREATE PROCEDURE TambahPelanggan(IN p_nama VARCHAR(100), IN p_kota VARCHAR(50))
                               BEGIN
                                   INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES (p_nama, p_kota);
                               END"
                ],
                'HitungPelanggan' => [
                    'drop' => "DROP PROCEDURE IF EXISTS HitungPelanggan",
                    'sql'  => "CREATE PROCEDURE HitungPelanggan(OUT jumlah INT)
                               BEGIN
                                   SELECT COUNT(*) INTO jumlah FROM tbl_pelanggan;
                               END"
                ],
                'TampilPelanggan' => [
                    'drop' => "DROP PROCEDURE IF EXISTS TampilPelanggan",
                    'sql'  => "CREATE PROCEDURE TampilPelanggan()
                               BEGIN
                                   SELECT * FROM tbl_pelanggan ORDER BY id_pelanggan DESC;
                               END"
                ],
                'CariPelanggan' => [
                    'drop' => "DROP PROCEDURE IF EXISTS CariPelanggan",
                    'sql'  => "CREATE PROCEDURE CariPelanggan(IN p_keyword VARCHAR(100))
                               BEGIN
                                   SELECT * FROM tbl_pelanggan 
                                   WHERE nama_pelanggan LIKE CONCAT('%', p_keyword, '%')
                                      OR kota LIKE CONCAT('%', p_keyword, '%');
                               END"
                ],
                'sp_TambahTransaksiBaru' => [
                    'drop' => "DROP PROCEDURE IF EXISTS sp_TambahTransaksiBaru",
                    'sql'  => "CREATE PROCEDURE sp_TambahTransaksiBaru(
                                   IN p_id_pelanggan INT,
                                   IN p_id_produk INT,
                                   IN p_jumlah INT,
                                   IN p_diskon_persen DECIMAL(5,2)
                               )
                               BEGIN
                                   DECLARE v_harga DECIMAL(12,2);
                                   DECLARE v_nama_produk VARCHAR(100);
                                   DECLARE v_subtotal DECIMAL(12,2);
                                   DECLARE v_potongan DECIMAL(12,2);
                                   DECLARE v_total_akhir DECIMAL(12,2);
                                   DECLARE v_id_transaksi INT;

                                   SELECT harga, nama_produk INTO v_harga, v_nama_produk
                                   FROM tbl_produk WHERE id_produk = p_id_produk;

                                   SET v_subtotal = HitungTotal(v_harga, p_jumlah);
                                   SET v_potongan = HitungDiskon(v_subtotal, p_diskon_persen);
                                   SET v_total_akhir = v_subtotal - v_potongan;

                                   INSERT INTO tbl_transaksi (tanggal, waktu_transaksi, id_pelanggan, total_belanja)
                                   VALUES (CURDATE(), NOW(), p_id_pelanggan, v_total_akhir);

                                   SET v_id_transaksi = LAST_INSERT_ID();

                                   INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga)
                                   VALUES (v_id_transaksi, p_id_produk, v_nama_produk, p_jumlah, v_harga);
                               END"
                ],
                'LaporanTransaksi' => [
                    'drop' => "DROP PROCEDURE IF EXISTS LaporanTransaksi",
                    'sql'  => "CREATE PROCEDURE LaporanTransaksi(IN p_tgl_mulai DATE, IN p_tgl_selesai DATE)
                               BEGIN
                                   SELECT t.id_transaksi, t.tanggal, p.nama_pelanggan, p.kota, t.total_belanja
                                   FROM tbl_transaksi t
                                   JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
                                   WHERE t.tanggal BETWEEN p_tgl_mulai AND p_tgl_selesai
                                   ORDER BY t.id_transaksi DESC;
                               END"
                ]
            ];

            foreach ($procedures as $pName => $pDef) {
                mysqli_query($conn, $pDef['drop']);
                if (mysqli_query($conn, $pDef['sql'])) {
                    logStatus($logs, "Stored Procedure '$pName' siap.", 'success');
                } else {
                    logStatus($logs, "Gagal membuat procedure '$pName': " . mysqli_error($conn), 'error');
                    $status_success = false;
                }
            }

            // 8. Buat Triggers
            $triggers = [
                'trg_kurangi_stok_dan_audit' => [
                    'drop' => "DROP TRIGGER IF EXISTS trg_kurangi_stok_dan_audit",
                    'sql'  => "CREATE TRIGGER trg_kurangi_stok_dan_audit
                               AFTER INSERT ON tbl_detail_transaksi
                               FOR EACH ROW
                               BEGIN
                                   IF NEW.id_produk IS NOT NULL THEN
                                       UPDATE tbl_produk 
                                       SET stok = stok - NEW.jumlah
                                       WHERE id_produk = NEW.id_produk;
                                   END IF;

                                   INSERT INTO tbl_log (tabel_terkait, aksi, keterangan)
                                   VALUES (
                                       'tbl_detail_transaksi', 
                                       'INSERT DETAIL', 
                                       CONCAT('Produk: ', NEW.nama_produk, ' | Jumlah: ', NEW.jumlah)
                                   );
                               END"
                ],
                'after_insert_pelanggan' => [
                    'drop' => "DROP TRIGGER IF EXISTS after_insert_pelanggan",
                    'sql'  => "CREATE TRIGGER after_insert_pelanggan
                               AFTER INSERT ON tbl_pelanggan
                               FOR EACH ROW
                               BEGIN
                                   INSERT INTO log_pelanggan (aktivitas, id_pelanggan)
                                   VALUES ('INSERT PELANGGAN', NEW.id_pelanggan);
                               END"
                ],
                'after_update_pelanggan' => [
                    'drop' => "DROP TRIGGER IF EXISTS after_update_pelanggan",
                    'sql'  => "CREATE TRIGGER after_update_pelanggan
                               AFTER UPDATE ON tbl_pelanggan
                               FOR EACH ROW
                               BEGIN
                                   INSERT INTO log_perubahan_pelanggan (id_pelanggan, nama_lama, nama_baru)
                                   VALUES (OLD.id_pelanggan, OLD.nama_pelanggan, NEW.nama_pelanggan);
                               END"
                ],
                'after_delete_pelanggan' => [
                    'drop' => "DROP TRIGGER IF EXISTS after_delete_pelanggan",
                    'sql'  => "CREATE TRIGGER after_delete_pelanggan
                               AFTER DELETE ON tbl_pelanggan
                               FOR EACH ROW
                               BEGIN
                                   INSERT INTO log_pelanggan (aktivitas, id_pelanggan)
                                   VALUES ('DELETE PELANGGAN', OLD.id_pelanggan);
                               END"
                ],
                'before_insert_transaksi' => [
                    'drop' => "DROP TRIGGER IF EXISTS before_insert_transaksi",
                    'sql'  => "CREATE TRIGGER before_insert_transaksi
                               BEFORE INSERT ON tbl_transaksi
                               FOR EACH ROW
                               BEGIN
                                   IF NEW.total_belanja < 0 THEN
                                       SIGNAL SQLSTATE '45000'
                                       SET MESSAGE_TEXT = 'Total belanja tidak boleh negatif';
                                   END IF;
                               END"
                ]
            ];

            foreach ($triggers as $tName => $tDef) {
                mysqli_query($conn, $tDef['drop']);
                if (mysqli_query($conn, $tDef['sql'])) {
                    logStatus($logs, "Database Trigger '$tName' siap.", 'success');
                } else {
                    logStatus($logs, "Gagal membuat trigger '$tName': " . mysqli_error($conn), 'error');
                    $status_success = false;
                }
            }

            // 9. Isi Data Awal / Seed Data jika tabel masih kosong
            // A. tbl_users
            $cek_u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_users"));
            if ($cek_u['c'] == 0) {
                $p_admin = password_hash('admin123', PASSWORD_DEFAULT);
                $p_kasir = password_hash('kasir123', PASSWORD_DEFAULT);
                $p_user  = password_hash('user123', PASSWORD_DEFAULT);

                mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) VALUES
                    ('admin', '$p_admin', 'Administrator Utama', 'Super Admin'),
                    ('kasir', '$p_kasir', 'Kasir TokoKita', 'Kasir POS'),
                    ('budi', '$p_user', 'Budi Santoso', 'Pelanggan')");
                logStatus($logs, "Data pengguna default dibuat (admin, kasir, budi).", 'success');
            }

            // B. tbl_kategori
            $cek_k = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_kategori"));
            if ($cek_k['c'] == 0) {
                mysqli_query($conn, "INSERT INTO tbl_kategori (nama_kategori) VALUES
                    ('Makanan & Minuman'),
                    ('Kebutuhan Pokok & Sembako'),
                    ('Elektronik & Aksesoris'),
                    ('Alat Tulis Kantor (ATK)'),
                    ('Perawatan Tubuh & Kebersihan')");
                logStatus($logs, "Data kategori produk diisi (5 kategori).", 'success');
            }

            // C. tbl_produk
            $cek_p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_produk"));
            if ($cek_p['c'] == 0) {
                mysqli_query($conn, "INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok) VALUES
                    (2, 'Minyak Goreng Sawit 2 Liter', 34000, 45),
                    (2, 'Beras Premium Ramos 5 Kg', 72000, 30),
                    (2, 'Gula Pasir Kristal Putih 1 Kg', 17500, 60),
                    (1, 'Kopi Robusta Bubuk 250g', 28000, 25),
                    (1, 'Mie Goreng Spesial Instan (Karton)', 115000, 18),
                    (1, 'Teh Kotak Jasmine 300ml', 4500, 100),
                    (3, 'Mouse Wireless Silent Click', 85000, 15),
                    (3, 'Flashdisk USB 3.0 32GB', 65000, 20),
                    (4, 'Buku Catatan Spiral Hardcover', 22000, 40),
                    (5, 'Sabun Mandi Antiseptik Cair 450ml', 26500, 35)");
                logStatus($logs, "Data produk katalog diisi (10 produk awal).", 'success');
            }

            // D. tbl_pelanggan
            $cek_pel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_pelanggan"));
            if ($cek_pel['c'] == 0) {
                mysqli_query($conn, "INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES
                    ('Budi Santoso', 'Ponorogo'),
                    ('Siti Aminah', 'Madiun'),
                    ('Ahmad Fauzi', 'Surabaya'),
                    ('Rina Wati', 'Ponorogo'),
                    ('Doni Pratama', 'Solo')");
                logStatus($logs, "Data pelanggan diisi (5 pelanggan default).", 'success');
            }

            // E. tbl_cabang
            $cek_cb = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_cabang"));
            if ($cek_cb['c'] == 0) {
                mysqli_query($conn, "INSERT INTO tbl_cabang (nama_cabang, kota) VALUES
                    ('Cabang Pusat Ponorogo', 'Ponorogo'),
                    ('Cabang Madiun Square', 'Madiun'),
                    ('Cabang Surabaya Barat', 'Surabaya')");
                logStatus($logs, "Data cabang toko diisi (3 cabang).", 'success');
            }

            // F. tbl_voucher
            $cek_v = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_voucher"));
            if ($cek_v['c'] == 0) {
                mysqli_query($conn, "INSERT INTO tbl_voucher (kode_voucher, persen_diskon, min_belanja) VALUES
                    ('HEMAT10', 10, 50000),
                    ('PROMO20', 20, 150000),
                    ('GAJIAN25', 25, 300000),
                    ('TOKOKITA50', 50, 500000)");
                logStatus($logs, "Data voucher diskon diisi (4 kupon promo).", 'success');
            }

            // G. tbl_topup
            $cek_tp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_topup"));
            if ($cek_tp['c'] == 0) {
                mysqli_query($conn, "INSERT INTO tbl_topup (id_pelanggan, nominal, metode, status) VALUES
                    (1, 200000, 'QRIS', 'Berhasil'),
                    (2, 150000, 'DANA', 'Berhasil'),
                    (3, 300000, 'Tunai Kasir', 'Berhasil'),
                    (1, 100000, 'ShopeePay', 'Berhasil')");
                logStatus($logs, "Data transaksi saldo awal diisi.", 'success');
            }

            // H. Transaksi Contoh (jika masih kosong)
            $cek_tx = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_transaksi"));
            if ($cek_tx['c'] == 0) {
                // Transaksi 1
                mysqli_query($conn, "INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES (CURDATE(), 1, 138000)");
                $tx1 = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES
                    ($tx1, 1, 'Minyak Goreng Sawit 2 Liter', 2, 34000),
                    ($tx1, 2, 'Beras Premium Ramos 5 Kg', 1, 72000)");

                // Transaksi 2
                mysqli_query($conn, "INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES (CURDATE(), 2, 93000)");
                $tx2 = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES
                    ($tx2, 4, 'Kopi Robusta Bubuk 250g', 1, 28000),
                    ($tx2, 8, 'Flashdisk USB 3.0 32GB', 1, 65000)");

                logStatus($logs, "Transaksi contoh beserta detail belanja berhasil di-generate.", 'success');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Auto-Installer - TokoKita</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-slate-100 font-sans min-h-screen p-4 md:p-8 flex items-center justify-center">

    <div class="max-w-4xl w-full bg-slate-900/90 border border-slate-700/80 rounded-3xl shadow-2xl p-6 md:p-8 space-y-6 backdrop-blur-xl">
        
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-600 p-3 rounded-2xl shadow-lg shadow-indigo-500/20 text-white">
                    <i data-lucide="database" class="w-7 h-7"></i>
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-black text-white tracking-tight">TokoKita Auto-Setup & Installer</h1>
                    <p class="text-xs text-slate-400">Inisialisasi Database MariaDB / MySQL • Tables, Views, Procedures, Functions & Triggers</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="login.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Halaman Login
                </a>
                <a href="dashboard.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Tombol Aksi Installer -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-800/60 border border-slate-700/50 p-5 rounded-2xl space-y-3">
                <div class="flex items-center gap-2 text-indigo-400 font-bold text-sm">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>Inisialisasi Cepat (Aman)</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Membuat database <code>db_tokokita</code>, seluruh tabel, stored procedure, function, trigger, serta menambahkan data contoh tanpa menghapus data yang sudah ada.
                </p>
                <a href="setup.php?action=install" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition shadow-lg shadow-indigo-600/30">
                    <i data-lucide="play" class="w-4 h-4"></i> Jalankan Setup / Update
                </a>
            </div>

            <div class="bg-slate-800/60 border border-rose-900/40 p-5 rounded-2xl space-y-3">
                <div class="flex items-center gap-2 text-rose-400 font-bold text-sm">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    <span>Fresh Reset Database</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Menghapus seluruh database <code>db_tokokita</code> dan membangun kembali dari awal dengan struktur dan data bawaan yang bersih.
                </p>
                <a href="setup.php?action=reset" onclick="return confirm('PERINGATAN: Seluruh data lama di db_tokokita akan dihapus bersih! Lanjutkan?')" class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition shadow-lg shadow-rose-600/30">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i> Reset & Install Ulang
                </a>
            </div>
        </div>

        <!-- Log Eksekusi -->
        <?php if (!empty($logs)): ?>
        <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                <h3 class="font-bold text-xs uppercase tracking-wider text-slate-300 flex items-center gap-2">
                    <i data-lucide="terminal" class="w-4 h-4 text-emerald-400"></i> Log Instalasi Sistem
                </h3>
                <span class="text-[11px] <?= $status_success ? 'text-emerald-400' : 'text-rose-400' ?> font-semibold">
                    <?= $status_success ? '● Berhasil Selesai' : '● Ada Kendala' ?>
                </span>
            </div>
            <div class="max-h-64 overflow-y-auto space-y-1.5 font-mono text-xs pr-2">
                <?php foreach ($logs as $l): ?>
                    <div class="flex items-start gap-2 <?= $l['tipe'] === 'error' ? 'text-rose-400' : ($l['tipe'] === 'warning' ? 'text-amber-400' : 'text-emerald-400') ?>">
                        <span class="text-slate-500 text-[10px] mt-0.5">[<?= $l['waktu'] ?>]</span>
                        <span><?= htmlspecialchars($l['pesan']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Informasi Akun Default & Struktur Database -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div class="bg-slate-800/40 border border-slate-700/40 p-4 rounded-2xl">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Super Admin</p>
                <p class="font-bold text-white text-sm">admin</p>
                <p class="text-xs text-indigo-400 font-mono mt-0.5">Password: admin123</p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/40 p-4 rounded-2xl">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Kasir POS</p>
                <p class="font-bold text-white text-sm">kasir</p>
                <p class="text-xs text-indigo-400 font-mono mt-0.5">Password: kasir123</p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/40 p-4 rounded-2xl">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Pelanggan Demo</p>
                <p class="font-bold text-white text-sm">budi</p>
                <p class="text-xs text-indigo-400 font-mono mt-0.5">Password: user123</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-slate-500 pt-3 border-t border-slate-800">
            Sistem Informasi Penjualan Terintegrasi • TokoKita POS & Enterprise • MariaDB Advanced SQL
        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
