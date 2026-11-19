<?php
require_once 'functions.php';
require_once 'duitku_config.php';
cekLogin();

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

$notif_sukses = "";
$notif_error  = "";
$hasil_metode_pembayaran = ambilMetodePembayaranDuitku(20000);
$metode_pembayaran_duitku = $hasil_metode_pembayaran['status'] ? $hasil_metode_pembayaran['methods'] : [];
$metode_default_checkout = isset($metode_pembayaran_duitku['NQ'])
    ? 'NQ'
    : (!empty($metode_pembayaran_duitku) ? key($metode_pembayaran_duitku) : 'COD (Bayar di Tempat)');

// 1. Tambah Item ke Keranjang Session dengan Pengecekan Stok Ketat
if (isset($_POST['masuk_keranjang'])) {
    $id_produk = (int)$_POST['id_produk'];
    $jumlah    = max(1, (int)$_POST['jumlah']);

    $q_cek = mysqli_query($conn, "SELECT *, HitungTotal(harga, $jumlah) AS subtotal FROM tbl_produk WHERE id_produk = $id_produk");
    $prod  = mysqli_fetch_assoc($q_cek);

    if ($prod) {
        $current_in_cart = isset($_SESSION['keranjang'][$id_produk]) ? (int)$_SESSION['keranjang'][$id_produk]['jumlah'] : 0;
        $total_diminta   = $current_in_cart + $jumlah;

        if ($prod['stok'] <= 0) {
            $notif_error = "Maaf, stok produk '{$prod['nama_produk']}' saat ini sudah habis!";
        } elseif ($total_diminta > $prod['stok']) {
            $sisa_bisa = max(0, $prod['stok'] - $current_in_cart);
            $notif_error = "Tidak bisa menambah barang! Stok '{$prod['nama_produk']}' tersisa {$prod['stok']} unit. " .
                           ($current_in_cart > 0 ? "(Sudah ada $current_in_cart unit di keranjang, maksimal hanya bisa tambah $sisa_bisa unit lagi)." : "");
        } else {
            if (isset($_SESSION['keranjang'][$id_produk])) {
                $_SESSION['keranjang'][$id_produk]['jumlah'] = $total_diminta;
                $res_sub  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT HitungTotal({$prod['harga']}, $total_diminta) AS sub"));
                $_SESSION['keranjang'][$id_produk]['subtotal'] = $res_sub['sub'];
            } else {
                $_SESSION['keranjang'][$id_produk] = [
                    'id_produk'   => $prod['id_produk'],
                    'nama_produk' => $prod['nama_produk'],
                    'harga'       => $prod['harga'],
                    'jumlah'      => $jumlah,
                    'subtotal'    => $prod['subtotal']
                ];
            }
            $notif_sukses = "{$prod['nama_produk']} ($jumlah unit) berhasil ditambahkan ke keranjang belanja!";
        }
    }
}

// 2. Ubah Jumlah Item (+ / -) di Keranjang dengan Batas Stok
if (isset($_GET['ubah_qty']) && isset($_GET['aksi'])) {
    $id_ubah = (int)$_GET['ubah_qty'];
    $aksi    = $_GET['aksi'];

    if (isset($_SESSION['keranjang'][$id_ubah])) {
        $q_stk = mysqli_query($conn, "SELECT nama_produk, harga, stok FROM tbl_produk WHERE id_produk = $id_ubah");
        $p_stk = mysqli_fetch_assoc($q_stk);

        if ($p_stk) {
            $qty_now = (int)$_SESSION['keranjang'][$id_ubah]['jumlah'];
            if ($aksi === 'tambah') {
                if ($qty_now + 1 > $p_stk['stok']) {
                    $_SESSION['pesan_error_keranjang'] = "Tidak bisa menambah lagi! Stok '{$p_stk['nama_produk']}' maksimal {$p_stk['stok']} unit.";
                } else {
                    $qty_new = $qty_now + 1;
                    $_SESSION['keranjang'][$id_ubah]['jumlah'] = $qty_new;
                    $res_sub = mysqli_fetch_assoc(mysqli_query($conn, "SELECT HitungTotal({$p_stk['harga']}, $qty_new) AS sub"));
                    $_SESSION['keranjang'][$id_ubah]['subtotal'] = $res_sub['sub'];
                }
            } elseif ($aksi === 'kurang') {
                $qty_new = $qty_now - 1;
                if ($qty_new <= 0) {
                    unset($_SESSION['keranjang'][$id_ubah]);
                } else {
                    $_SESSION['keranjang'][$id_ubah]['jumlah'] = $qty_new;
                    $res_sub = mysqli_fetch_assoc(mysqli_query($conn, "SELECT HitungTotal({$p_stk['harga']}, $qty_new) AS sub"));
                    $_SESSION['keranjang'][$id_ubah]['subtotal'] = $res_sub['sub'];
                }
            }
        }
    }
    header("Location: keranjang.php");
    exit;
}

// Tangkap notifikasi pesan dari redirect ubah_qty
if (isset($_SESSION['pesan_error_keranjang'])) {
    $notif_error = $_SESSION['pesan_error_keranjang'];
    unset($_SESSION['pesan_error_keranjang']);
}

// 3. Hapus 1 Item atau Kosongkan Keranjang
if (isset($_GET['hapus_item'])) {
    $id_hapus = (int)$_GET['hapus_item'];
    unset($_SESSION['keranjang'][$id_hapus]);
    header("Location: keranjang.php");
    exit;
}
if (isset($_GET['kosongkan'])) {
    $_SESSION['keranjang'] = [];
    header("Location: keranjang.php");
    exit;
}

// Cari ID Pelanggan dari session login jika role Pelanggan
$id_pel_login = isPelanggan() ? getIdPelangganLogin() : 0;

// Ambil Saldo Dompet Digital Pelanggan & Profil Alamat Pengiriman
$saldo_pelanggan_login = 0;
$data_pel_login = null;
if ($id_pel_login > 0) {
    $q_saldo = mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) AS total_saldo FROM tbl_topup WHERE id_pelanggan = $id_pel_login AND status = 'Berhasil'");
    if ($r_s = mysqli_fetch_assoc($q_saldo)) {
        $saldo_pelanggan_login = (float)$r_s['total_saldo'];
    }
    $qp_info = mysqli_query($conn, "SELECT * FROM tbl_pelanggan WHERE id_pelanggan = $id_pel_login LIMIT 1");
    if ($qp_info && mysqli_num_rows($qp_info) > 0) {
        $data_pel_login = mysqli_fetch_assoc($qp_info);
    }
}
$alamat_profil_default  = $data_pel_login['alamat'] ?? '';
$telepon_profil_default = $data_pel_login['no_telepon'] ?? '';
$kota_profil_default    = $data_pel_login['kota'] ?? '';

$data_sukses_checkout = null;
$return_owner_filter = isPelanggan() ? "AND id_pelanggan = $id_pel_login" : '';

// Duitku redirects back with resultCode; confirm the result server-to-server before showing success.
if (
    isset($_GET['merchantOrderId'], $_GET['resultCode'], $_GET['reference'])
    && preg_match('/^TRX-(\d+)$/', (string)$_GET['merchantOrderId'], $orderMatch)
    && $_GET['resultCode'] === '00'
) {
    $return_trx_id = (int)$orderMatch[1];
    $return_reference = trim((string)$_GET['reference']);
    $q_return_trx = mysqli_query($conn, "SELECT * FROM tbl_transaksi WHERE id_transaksi = $return_trx_id $return_owner_filter LIMIT 1");
    $return_trx = $q_return_trx ? mysqli_fetch_assoc($q_return_trx) : null;

    if ($return_trx && !empty($return_trx['duitku_reference']) && hash_equals($return_trx['duitku_reference'], $return_reference)) {
        $status_duitku = cekStatusTransaksiDuitku((string)$_GET['merchantOrderId']);
        $nominal_terverifikasi = $status_duitku['status'] && isset($status_duitku['amount'])
            ? (int)round((float)$status_duitku['amount'])
            : -1;
        $ref_terverifikasi = $status_duitku['status'] ? (string)($status_duitku['reference'] ?? '') : '';

        if (
            $status_duitku['status']
            && (string)$status_duitku['statusCode'] === '00'
            && $nominal_terverifikasi === (int)round((float)$return_trx['total_belanja'])
            && hash_equals($return_trx['duitku_reference'], $ref_terverifikasi)
        ) {
            $refEsc = mysqli_real_escape_string($conn, $ref_terverifikasi);
            $update_return = mysqli_query($conn, "UPDATE tbl_transaksi SET status_pembayaran='Lunas', ref_pembayaran='$refEsc', duitku_reference='$refEsc' WHERE id_transaksi=$return_trx_id $return_owner_filter");
            if ($update_return) {
                $metode_transaksi = $return_trx['metode_pembayaran'] ?? 'Duitku';
                $data_sukses_checkout = [
                    'id_transaksi' => $return_trx_id,
                    'total_belanja' => (float)$return_trx['total_belanja'],
                    'nominal_bayar' => (float)$return_trx['total_belanja'],
                    'metode_bayar' => namaMetodeDuitku($metode_transaksi, $metode_transaksi),
                    'status_bayar' => 'Lunas',
                    'ref_barcode' => $ref_terverifikasi,
                    'alamat_pengiriman' => $return_trx['alamat_pengiriman'] ?? '',
                    'no_telepon' => $return_trx['no_telepon'] ?? '',
                    'catatan_pengiriman' => $return_trx['catatan_pengiriman'] ?? ''
                ];
                $_SESSION['keranjang'] = [];
                $notif_sukses = 'Pembayaran Duitku berhasil dan telah terverifikasi.';
            } else {
                $notif_error = 'Pembayaran terverifikasi oleh Duitku, tetapi status transaksi gagal diperbarui: ' . mysqli_error($conn);
            }
        } else {
            $notif_error = 'Pembayaran belum terverifikasi sebagai lunas. Periksa kembali status transaksi atau hubungi admin.';
        }
    } elseif ($return_trx) {
        $notif_error = 'Referensi pembayaran tidak cocok dengan transaksi ini.';
    }
}

// 4. Proses Checkout Keranjang dengan Validasi Stok Ketat & Metode Pembayaran Lengkap
if (isset($_POST['checkout_keranjang'])) {
    if (isPelanggan() && $id_pel_login <= 0) {
        $notif_error = 'Akun pelanggan belum terhubung dengan data member. Hubungi kasir sebelum checkout.';
    } elseif (empty($_SESSION['keranjang'])) {
        $notif_error = "Keranjang belanja Anda masih kosong! Silakan pilih produk terlebih dahulu.";
    } else {
        $id_pelanggan      = isPelanggan() ? $id_pel_login : (int)$_POST['id_pelanggan'];
        $diskon_persen     = (float)$_POST['diskon_persen'];
        $metode_bayar      = isset($_POST['metode_pembayaran']) ? mysqli_real_escape_string($conn, trim($_POST['metode_pembayaran'])) : 'QRIS Instan';
        $metode_duitku_map = [
            'QRIS Instan' => 'NQ',
            'SPayLater / ShopeePay' => 'SP',
            'DANA Dompet Digital' => 'DA',
            'Virtual Account BCA' => 'BC'
        ];
        if (isset($metode_duitku_map[$metode_bayar])) {
            $metode_bayar = $metode_duitku_map[$metode_bayar];
        }
        $ref_bayar         = isset($_POST['ref_pembayaran']) && !empty($_POST['ref_pembayaran']) ? mysqli_real_escape_string($conn, trim($_POST['ref_pembayaran'])) : '';
        $alamat_pengiriman = isset($_POST['alamat_pengiriman']) ? mysqli_real_escape_string($conn, trim($_POST['alamat_pengiriman'])) : '';
        $no_telepon        = isset($_POST['no_telepon']) ? mysqli_real_escape_string($conn, trim($_POST['no_telepon'])) : '';
        $catatan_kirim     = isset($_POST['catatan_pengiriman']) ? mysqli_real_escape_string($conn, trim($_POST['catatan_pengiriman'])) : '';
        $simpan_profil     = isset($_POST['simpan_profil']) ? 1 : 0;

        // 1. Validasi Alamat Pengiriman
        if (empty($alamat_pengiriman)) {
            $notif_error = "Harap masukkan alamat pengiriman sebelum menyelesaikan pembayaran pesanan!";
        }

        // 2. VALIDASI KETAT STOK BARANG SEBELUM CHECKOUT (TIDAK BISA CHECKOUT LEBIH DARI STOK)
        if (empty($notif_error)) {
            foreach ($_SESSION['keranjang'] as $item) {
                $id_chk  = (int)$item['id_produk'];
                $jml_chk = (int)$item['jumlah'];

                $q_stk_chk = mysqli_query($conn, "SELECT nama_produk, stok FROM tbl_produk WHERE id_produk = $id_chk");
                $r_stk_chk = mysqli_fetch_assoc($q_stk_chk);

                if (!$r_stk_chk) {
                    $notif_error = "Checkout dibatalkan! Produk '{$item['nama_produk']}' tidak ditemukan dalam katalog.";
                    break;
                }
                if ($r_stk_chk['stok'] <= 0) {
                    $notif_error = "Checkout dibatalkan! Stok '{$r_stk_chk['nama_produk']}' saat ini sudah habis. Harap hapus produk dari keranjang.";
                    break;
                }
                if ($jml_chk > $r_stk_chk['stok']) {
                    $notif_error = "Checkout ditolak! Jumlah pesanan untuk '{$r_stk_chk['nama_produk']}' ($jml_chk unit) melebihi stok yang tersedia ({$r_stk_chk['stok']} unit). Harap sesuaikan jumlah pesanan Anda!";
                    break;
                }
            }
        }

        // Generate kode referensi invoice otomatis jika belum ada
        if (empty($ref_bayar)) {
            if (strpos($metode_bayar, 'QRIS') !== false) {
                $ref_bayar = 'QRIS-' . date('Ymd') . '-' . rand(1000, 9999);
            } elseif (strpos($metode_bayar, 'SPayLater') !== false) {
                $ref_bayar = 'SPL-' . date('Ymd') . '-' . rand(1000, 9999);
            } elseif (strpos($metode_bayar, 'DANA') !== false) {
                $ref_bayar = 'DANA-' . date('Ymd') . '-' . rand(1000, 9999);
            } elseif (strpos($metode_bayar, 'Saldo') !== false) {
                $ref_bayar = 'MBR-' . date('Ymd') . '-' . rand(1000, 9999);
            } elseif (strpos($metode_bayar, 'BCA') !== false) {
                $ref_bayar = '89901' . rand(10000000, 99999999);
            } elseif (strpos($metode_bayar, 'BRI') !== false) {
                $ref_bayar = '12800' . rand(10000000, 99999999);
            } elseif (strpos($metode_bayar, 'COD') !== false) {
                $ref_bayar = 'COD-' . date('Ymd') . '-' . rand(1000, 9999);
            } else {
                $ref_bayar = 'TRX-' . date('Ymd') . '-' . rand(1000, 9999);
            }
        }

        // Hitung Grand Total dari Keranjang
        $total_sebelum_diskon = 0;
        foreach ($_SESSION['keranjang'] as $item) {
            $total_sebelum_diskon += $item['subtotal'];
        }

        // Jalankan Function Database HitungDiskon
        $q_hitung = mysqli_query($conn, "
            SELECT ($total_sebelum_diskon - HitungDiskon($total_sebelum_diskon, $diskon_persen)) AS total_bersih
        ");
        $kalkulasi = mysqli_fetch_assoc($q_hitung);
        $total_bersih = $kalkulasi['total_bersih'];
       $total_tagihan_bulat = (int)round((float)$total_bersih);
        $is_gateway = isMetodeDuitku($metode_bayar);
        if (!$is_gateway && !in_array($metode_bayar, ['Saldo TokoKita Pay', 'COD (Bayar di Tempat)'], true)) {
            $notif_error = 'Metode pembayaran tidak valid. Pilih metode yang tersedia.';
        }

        $pesan_batas_duitku = validasiBatasNominalDuitku($metode_bayar, $total_tagihan_bulat);
        if ($pesan_batas_duitku !== '') {
            $notif_error = $pesan_batas_duitku;
        }
        if ($is_gateway && empty($notif_error)) {
            if (!duitku_konfigurasi_siap()) {
                $notif_error = 'Konfigurasi Duitku belum siap. Periksa DUITKU_MERCHANT_CODE dan DUITKU_API_KEY.';
            } else {
                $metode_aktif_checkout = ambilMetodePembayaranDuitku($total_tagihan_bulat);
                if (empty($metode_aktif_checkout['status'])) {
                    $notif_error = $metode_aktif_checkout['pesan'] ?? 'Gagal memeriksa metode pembayaran Duitku.';
                } elseif (!isset($metode_aktif_checkout['methods'][$metode_bayar])) {
                    $notif_error = 'Metode pembayaran ' . namaMetodeDuitku($metode_bayar) . ' tidak aktif untuk nominal transaksi ini.';
                }
            }
        }
        $is_cod = (strpos($metode_bayar, 'COD') !== false);

        $nominal_bayar = $total_tagihan_bulat;

        // 3. Validasi Saldo Dompet jika bayar pakai Saldo TokoKita Pay
        if (empty($notif_error) && strpos($metode_bayar, 'Saldo') !== false) {
            // Ambil saldo real-time dari database
            $q_cek_saldo_db = mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) AS total_saldo FROM tbl_topup WHERE id_pelanggan = $id_pelanggan AND status = 'Berhasil'");
            $r_cs = mysqli_fetch_assoc($q_cek_saldo_db);
            $saldo_real = $r_cs ? (float)$r_cs['total_saldo'] : 0;

            if ($saldo_real < $total_bersih) {
                $notif_error = "Saldo Dompet TokoKita Pay Anda tidak mencukupi! Saldo Anda: " . rupiah($saldo_real) . ", Total Tagihan: " . rupiah($total_bersih) . ". Silakan isi saldo terlebih dahulu atau pilih metode pembayaran lain.";
            }
        }

        // Eksekusi Transaksi Database (TCL COMMIT & ROLLBACK)
        if (empty($notif_error)) {
            $status_bayar = $is_gateway ? 'Pending' : ((strpos($metode_bayar, 'COD') !== false) ? 'Menunggu Pembayaran' : 'Lunas');

            mysqli_begin_transaction($conn);

            $ok_header = mysqli_query($conn, "
                INSERT INTO tbl_transaksi (tanggal, waktu_transaksi, id_pelanggan, total_belanja, metode_pembayaran, status_pembayaran, ref_pembayaran, alamat_pengiriman, no_telepon, catatan_pengiriman)
                VALUES (CURDATE(), NOW(), $id_pelanggan, $total_bersih, '$metode_bayar', '$status_bayar', '$ref_bayar', '$alamat_pengiriman', '$no_telepon', '$catatan_kirim')
            ");
            $id_trx_baru = mysqli_insert_id($conn);

            $ok_detail = true;
            $pesan_err_detail = "";
            foreach ($_SESSION['keranjang'] as $item) {
                $id_p = (int)$item['id_produk'];
                $nm_p = mysqli_real_escape_string($conn, $item['nama_produk']);
                $jml  = (int)$item['jumlah'];
                $hrg  = (float)$item['harga'];

                // Insert ke tbl_detail_transaksi (Trigger otomatis memotong stok & memvalidasi stok >= jumlah)
                $ins_det = mysqli_query($conn, "
                    INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga)
                    VALUES ($id_trx_baru, $id_p, '$nm_p', $jml, $hrg)
                ");
                if (!$ins_det) {
                    $ok_detail = false;
                    $pesan_err_detail = mysqli_error($conn);
                    break;
                }
            }

            // Jika bayar menggunakan Saldo TokoKita Pay, potong saldo member
            if ($ok_header && $ok_detail && strpos($metode_bayar, 'Saldo') !== false) {
                $debit_nominal = -abs($total_bersih);
                $ok_saldo = mysqli_query($conn, "
                    INSERT INTO tbl_topup (id_pelanggan, nominal, metode, status) 
                    VALUES ($id_pelanggan, $debit_nominal, 'Pembayaran Belanja #TRX-$id_trx_baru', 'Berhasil')
                ");
                if (!$ok_saldo) {
                    $ok_detail = false;
                    $pesan_err_detail = "Gagal memotong saldo member: " . mysqli_error($conn);
                }
            }

            if ($ok_header && $ok_detail) {
                // Simpan perubahan alamat ke profil jika opsi dicentang
                if ($simpan_profil && $id_pelanggan > 0) {
                    mysqli_query($conn, "UPDATE tbl_pelanggan SET alamat = '$alamat_pengiriman', no_telepon = '$no_telepon' WHERE id_pelanggan = $id_pelanggan");
                    $alamat_profil_default = $alamat_pengiriman;
                    $telepon_profil_default = $no_telepon;
                }

                if ($is_gateway) {
                    if (!duitku_konfigurasi_siap()) {
                        mysqli_rollback($conn);
                        $notif_error = "Konfigurasi Duitku belum lengkap. Isi DUITKU_MERCHANT_CODE dan DUITKU_API_KEY.";
                    } else {
                        $r_pel_duitku = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_pelanggan FROM tbl_pelanggan WHERE id_pelanggan = $id_pelanggan"));
                        $nama_pembeli = $r_pel_duitku['nama_pelanggan'] ?? 'Pelanggan TokoKita';
                        $duitku = buatTransaksiDuitku("TRX-" . $id_trx_baru, $total_bersih, $metode_bayar, "Belanja TokoKita #$id_trx_baru", $nama_pembeli);

                        if ($duitku['status']) {
                            $ref_url = mysqli_real_escape_string($conn, $duitku['paymentUrl']);
                            $ref_id = mysqli_real_escape_string($conn, $duitku['reference']);
                            $update_duitku = mysqli_query($conn, "UPDATE tbl_transaksi SET status_pembayaran='Pending', ref_pembayaran='$ref_id', duitku_reference='$ref_id', duitku_url='$ref_url' WHERE id_transaksi=$id_trx_baru");
                            if ($update_duitku) {
                                mysqli_commit($conn);
                                $_SESSION['keranjang'] = [];
                                header("Location: " . $duitku['paymentUrl']);
                                exit;
                            }
                            mysqli_rollback($conn);
                            $notif_error = "Gagal menyimpan data invoice Duitku.";
                        } else {
                            mysqli_rollback($conn);
                            $notif_error = "Gagal membuat invoice Duitku: " . $duitku['pesan'];
                        }
                    }
                } else {
                    mysqli_commit($conn);
                    $items_count_checkout = count($_SESSION['keranjang']);
                    $_SESSION['keranjang'] = [];

                    $data_sukses_checkout = [
                        'id_transaksi'       => $id_trx_baru,
                        'total_belanja'      => $total_bersih,
                        'nominal_bayar'      => $nominal_bayar,
                        'metode_bayar'       => $metode_bayar,
                        'status_bayar'       => $status_bayar,
                        'ref_barcode'        => $ref_bayar,
                        'items_count'        => $items_count_checkout,
                        'alamat_pengiriman'  => $alamat_pengiriman,
                        'no_telepon'         => $no_telepon,
                        'catatan_pengiriman' => $catatan_kirim
                    ];

                    $notif_sukses = "Pembayaran Berhasil! Transaksi #TRX-" . str_pad($id_trx_baru, 4, '0', STR_PAD_LEFT) . " telah dikonfirmasi via $metode_bayar. Stok produk berhasil dipotong otomatis.";
                    mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_transaksi', 'CHECKOUT PELANGGAN', 'Transaksi #$id_trx_baru via $metode_bayar total " . rupiah($total_bersih) . " ke alamat: $alamat_pengiriman')");

                    if ($id_pel_login > 0) {
                        $q_saldo_new = mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) AS total_saldo FROM tbl_topup WHERE id_pelanggan = $id_pel_login AND status = 'Berhasil'");
                        if ($r_sn = mysqli_fetch_assoc($q_saldo_new)) {
                            $saldo_pelanggan_login = (float)$r_sn['total_saldo'];
                        }
                    }
                }
            } else {
                mysqli_rollback($conn);
                $notif_error = "Checkout Dibatalkan (TCL ROLLBACK)! " . ($pesan_err_detail ? $pesan_err_detail : "Stok produk tidak mencukupi atau database sibuk.");
            }
        }
    }
}

$q_produk    = mysqli_query($conn, "SELECT p.*, k.nama_kategori FROM tbl_produk p LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori WHERE p.stok > 0 ORDER BY p.id_produk DESC");
$q_pelanggan = mysqli_query($conn, "SELECT * FROM view_pelanggan ORDER BY nama_pelanggan ASC");

$jml_keranjang = count($_SESSION['keranjang']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100/70 text-slate-800 font-sans min-h-screen selection:bg-indigo-500 selection:text-white">

    <!-- Global TokoKita Navbar -->
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        
        <!-- Modal / Banner Sukses Pembayaran Berhasil -->
        <?php if ($data_sukses_checkout): ?>
            <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-950 p-6 sm:p-8 rounded-3xl border-2 border-emerald-500/50 text-white shadow-2xl relative overflow-hidden space-y-4">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4 text-left">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-white flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/30">
                            <i data-lucide="check-circle-2" class="w-8 h-8"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="bg-emerald-500 text-white text-[9.5px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider">
                                    <?= htmlspecialchars($data_sukses_checkout['status_bayar']) ?>
                                </span>
                                <span class="text-xs font-mono font-bold text-slate-300">#TRX-<?= str_pad($data_sukses_checkout['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            <h2 class="text-lg sm:text-xl font-black text-white mt-0.5">
                                <?= strpos($data_sukses_checkout['metode_bayar'], 'COD') !== false ? 'Pesanan COD Berhasil Dibuat!' : 'Pembayaran Berhasil Dikonfirmasi!' ?>
                            </h2>
                            <p class="text-xs text-slate-300 mt-1">
                                Metode: <span class="text-amber-400 font-black"><?= htmlspecialchars($data_sukses_checkout['metode_bayar']) ?></span> • Ref: <span class="font-mono text-emerald-400"><?= htmlspecialchars($data_sukses_checkout['ref_barcode']) ?></span>
                            </p>
                            <p class="text-xs text-slate-300 mt-1">
                                <?= strpos($data_sukses_checkout['metode_bayar'], 'COD') !== false ? 'Tagihan saat barang diterima:' : 'Nominal pembayaran:' ?>
                                <span class="font-black text-white"><?= rupiah($data_sukses_checkout['nominal_bayar']) ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                        <?php if (!isPelanggan()): ?>
                        <a href="struk.php?id=<?= $data_sukses_checkout['id_transaksi'] ?>&metode=<?= urlencode($data_sukses_checkout['metode_bayar']) ?>&bayar=<?= $data_sukses_checkout['total_belanja'] ?>" 
                           target="_blank"
                           class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-black shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition active:scale-95">
                            <i data-lucide="printer" class="w-4 h-4 text-indigo-200"></i> Cetak Struk 58mm
                        </a>
                        <?php else: ?>
                        <span class="bg-white/10 text-slate-300 border border-white/20 px-3.5 py-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5 cursor-default">
                            <i data-lucide="receipt" class="w-4 h-4 text-amber-300"></i> Struk Dicetak oleh Kasir
                        </span>
                        <?php endif; ?>
                        <a href="kategori.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                            <i data-lucide="shopping-bag" class="w-4 h-4"></i> Belanja Lagi
                        </a>
                    </div>
                </div>

                <!-- Detail Alamat Pengiriman -->
                <?php if (!empty($data_sukses_checkout['alamat_pengiriman'])): ?>
                <div class="relative z-10 bg-white/10 border border-white/15 p-3.5 rounded-2xl text-xs flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center shrink-0 mt-0.5 border border-emerald-400/30">
                        <i data-lucide="truck" class="w-4 h-4"></i>
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-300 font-extrabold text-[11px] uppercase tracking-wider">Alamat Pengiriman Pesanan:</span>
                            <span class="text-[9.5px] bg-emerald-500/30 text-emerald-200 px-1.5 py-0.2 rounded font-bold">Siap Dikirim</span>
                        </div>
                        <p class="font-bold text-white"><?= htmlspecialchars($data_sukses_checkout['alamat_pengiriman']) ?></p>
                        <p class="text-[11px] text-slate-300">
                            No. Handphone / WhatsApp: <span class="font-bold text-white"><?= htmlspecialchars($data_sukses_checkout['no_telepon']) ?></span>
                            <?php if (!empty($data_sukses_checkout['catatan_pengiriman'])): ?>
                                • Catatan Kurir: <span class="italic text-amber-200">"<?= htmlspecialchars($data_sukses_checkout['catatan_pengiriman']) ?>"</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Notifikasi -->
        <?php if ($notif_sukses && !$data_sukses_checkout): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3.5 rounded-2xl flex items-center justify-between gap-3 text-xs font-bold shadow-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </div>
                    <span><?= $notif_sukses ?></span>
                </div>
                <a href="kategori.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0">
                    + Belanja Lagi
                </a>
            </div>
        <?php endif; ?>

        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3.5 rounded-2xl flex items-center gap-2.5 text-xs font-bold shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i> 
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Katalog Pilih Produk (Kiri - 7 Kolom) -->
            <div class="lg:col-span-7 bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                            <i data-lucide="shopping-bag" class="w-4 h-4 text-indigo-600"></i> Pilih Produk Katalog
                        </h2>
                        <p class="text-[11px] text-slate-400">Pilih item untuk langsung dimasukkan ke keranjang</p>
                    </div>
                    <a href="kategori.php" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                        Semua Kategori <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Live Filter Input -->
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariItemKeranjang" onkeyup="filterItemKeranjang()" placeholder="Cari nama barang atau barcode..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 max-h-[620px] overflow-y-auto pr-1">
                    <?php while ($pr = mysqli_fetch_assoc($q_produk)): ?>
                    <form method="POST" class="kartu-item border border-slate-200/70 rounded-2xl p-4 hover:border-indigo-500 hover:shadow-md transition flex flex-col justify-between bg-white group">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[9px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-md">
                                    <?= htmlspecialchars($pr['nama_kategori'] ?? 'Umum') ?>
                                </span>
                                <span class="text-[10.5px] font-bold text-slate-400">
                                    Stok: <?= $pr['stok'] ?>
                                </span>
                            </div>
                            <h3 class="font-extrabold text-xs text-slate-800 group-hover:text-indigo-600 transition line-clamp-1">
                                <?= htmlspecialchars($pr['nama_produk']) ?>
                            </h3>
                            <div class="font-mono text-[10px] text-slate-400 mt-0.5 flex items-center gap-1">
                                <i data-lucide="barcode" class="w-3 h-3 text-slate-400"></i>
                                <span><?= htmlspecialchars($pr['barcode'] ?? ('899' . str_pad($pr['id_produk'], 8, '0', STR_PAD_LEFT))) ?></span>
                            </div>
                            <p class="text-emerald-600 font-black text-sm mt-2"><?= rupiah($pr['harga']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 mt-3 pt-2.5 border-t border-slate-100">
                            <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                            <input type="number" name="jumlah" value="1" min="1" max="<?= $pr['stok'] ?>" 
                                class="w-14 border border-slate-300 rounded-xl p-2 text-xs text-center font-black focus:ring-2 focus:ring-indigo-500">
                            <button type="submit" name="masuk_keranjang" 
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black py-2 px-3 rounded-xl flex items-center justify-center gap-1.5 transition active:scale-95 shadow-md shadow-indigo-600/20">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Beli
                            </button>
                        </div>
                    </form>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Panel Rincian Keranjang & Checkout (Kanan - 5 Kolom) -->
            <div class="lg:col-span-5 bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4 sticky top-24">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-4 h-4 text-emerald-600"></i>
                        <span><?= isPelanggan() ? 'Keranjang Belanja Anda' : 'Keranjang Kasir Multi-Item' ?></span>
                    </h2>
                    <?php if (!empty($_SESSION['keranjang'])): ?>
                        <a href="?kosongkan=1" onclick="return confirm('Kosongkan semua isi keranjang?')" 
                           class="text-[11px] text-rose-500 hover:text-rose-700 font-extrabold bg-rose-50 px-2 py-1 rounded-lg hover:bg-rose-100 transition">
                            Kosongkan
                        </a>
                    <?php endif; ?>
                </div>

                <?php 
                $grand_total = 0;
                if (empty($_SESSION['keranjang'])): ?>
                    <div class="text-center py-12 text-slate-400 space-y-2">
                        <div class="w-14 h-14 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto text-slate-300 shadow-inner">
                            <i data-lucide="shopping-cart" class="w-7 h-7"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-600">Keranjang belanja Anda masih kosong</p>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto">Silakan pilih produk dari katalog di sebelah kiri untuk mulai berbelanja.</p>
                        <a href="kategori.php" class="inline-flex items-center gap-1.5 mt-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs px-4 py-2 rounded-xl transition shadow-md shadow-indigo-600/25">
                            <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i> Buka Katalog Produk →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                        <?php 
                        $ada_melebihi_stok = false;
                        foreach ($_SESSION['keranjang'] as $item): 
                            $grand_total += $item['subtotal'];
                            $q_cek_stk_now = mysqli_query($conn, "SELECT stok FROM tbl_produk WHERE id_produk = {$item['id_produk']}");
                            $r_cek_stk_now = mysqli_fetch_assoc($q_cek_stk_now);
                            $stok_tersisa  = $r_cek_stk_now ? (int)$r_cek_stk_now['stok'] : 0;
                            $over_limit    = ($item['jumlah'] > $stok_tersisa);
                            if ($over_limit) $ada_melebihi_stok = true;
                        ?>
                        <div class="flex justify-between items-center p-3 rounded-2xl border text-xs transition <?= $over_limit ? 'bg-rose-50 border-rose-300 ring-2 ring-rose-400/40' : 'bg-slate-50 hover:bg-slate-100/70 border-slate-200/80' ?>">
                            <div class="pr-2 truncate flex-1 min-w-0">
                                <p class="font-extrabold text-slate-800 truncate"><?= htmlspecialchars($item['nama_produk']) ?></p>
                                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                    <!-- Pengatur Jumlah Item (+ / -) -->
                                    <div class="inline-flex items-center bg-white border border-slate-300 rounded-xl overflow-hidden shadow-xs">
                                        <a href="?ubah_qty=<?= $item['id_produk'] ?>&aksi=kurang" class="w-6 h-6 flex items-center justify-center font-black text-slate-700 hover:bg-slate-100 active:bg-slate-200 transition" title="Kurangi 1">−</a>
                                        <span class="px-2 font-black text-xs text-slate-900"><?= $item['jumlah'] ?></span>
                                        <a href="?ubah_qty=<?= $item['id_produk'] ?>&aksi=tambah" class="w-6 h-6 flex items-center justify-center font-black text-slate-700 hover:bg-slate-100 active:bg-slate-200 transition <?= ($item['jumlah'] >= $stok_tersisa) ? 'opacity-30 pointer-events-none' : '' ?>" title="Tambah 1">+</a>
                                    </div>
                                    <span class="text-[11px] text-slate-500 font-medium">× <?= rupiah($item['harga']) ?></span>
                                    <span class="text-[10px] font-bold <?= $over_limit ? 'text-rose-600 bg-rose-100 px-1.5 py-0.2 rounded' : 'text-slate-400' ?>">(Stok: <?= $stok_tersisa ?>)</span>
                                </div>
                                <?php if ($over_limit): ?>
                                    <p class="text-[10px] text-rose-600 font-black mt-1.5 flex items-center gap-1">
                                        <i data-lucide="alert-triangle" class="w-3 h-3 shrink-0"></i>
                                        <span>Pesanan melebihi stok! Maksimal <?= $stok_tersisa ?> unit.</span>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right shrink-0 ml-2 flex flex-col items-end justify-between">
                                <p class="font-black text-indigo-700 text-xs"><?= rupiah($item['subtotal']) ?></p>
                                <a href="?hapus_item=<?= $item['id_produk'] ?>" class="text-[10.5px] text-rose-500 hover:text-rose-700 font-bold hover:underline inline-flex items-center gap-0.5 mt-2">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($ada_melebihi_stok): ?>
                        <div class="bg-rose-50 border border-rose-300 text-rose-800 p-2.5 rounded-xl text-[11px] font-bold flex items-center gap-2">
                            <i data-lucide="shield-alert" class="w-4 h-4 text-rose-600 shrink-0"></i>
                            <span>Terdapat barang yang melebihi batas stok. Harap kurangi jumlah sebelum membuat pesanan!</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['keranjang'])): ?>
                <form method="POST" id="formCheckoutKeranjang" onsubmit="return validasiCheckoutDuitku()" class="pt-3 border-t border-slate-100 space-y-4 text-xs">
                    <!-- Grand Total Banner -->
                    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-4 rounded-2xl flex justify-between items-center shadow-lg border border-indigo-900/50">
                        <div>
                            <span class="text-[11px] text-slate-400 block font-medium">Total Tagihan Belanja:</span>
                            <span class="text-[10px] text-indigo-300 font-mono">Function <code>HitungTotal</code></span>
                        </div>
                        <div class="text-right">
                            <span id="labelGrandTotal" class="text-lg font-black text-emerald-400"><?= rupiah($grand_total) ?></span>
                            <span id="labelKetDiskon" class="text-[9.5px] text-amber-300 block hidden">Diskon Diterapkan</span>
                        </div>
                    </div>

                    <?php if (isPelanggan()): ?>
                        <!-- Identitas Pelanggan Otomatis Terkunci untuk Akun Pelanggan -->
                        <input type="hidden" name="id_pelanggan" value="<?= $id_pel_login ?>">
                        <div class="bg-indigo-50 border border-indigo-200 p-3 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-indigo-500 font-bold uppercase block">Akun Pemesan:</span>
                                <span class="text-xs font-black text-indigo-950"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                            </div>
                            <span class="bg-indigo-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-md">Verified Mall</span>
                        </div>
                    <?php else: ?>
                        <!-- Pilihan Pelanggan Kasir POS -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Pelanggan Kasir:</label>
                            <select name="id_pelanggan" required class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <?php while ($pel = mysqli_fetch_assoc($q_pelanggan)): ?>
                                    <option value="<?= $pel['id_pelanggan'] ?>">
                                        <?= htmlspecialchars($pel['nama_pelanggan']) ?> (<?= htmlspecialchars($pel['kota']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- SEKSI ALAMAT PENGIRIMAN PESANAN (Sebelum Membuat Pesanan) -->
                    <div class="bg-gradient-to-br from-rose-50/70 via-orange-50/40 to-white p-4 rounded-3xl border-2 border-rose-200/90 space-y-3 shadow-sm">
                        <div class="flex items-center justify-between border-b border-rose-200/60 pb-2.5">
                            <span class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="map-pin" class="w-4 h-4 text-[#ee4d2d]"></i>
                                <span>1. Masukkan Alamat Pengiriman Pesanan</span>
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" onclick="kosongkanAlamatKeranjang()" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-extrabold hover:underline cursor-pointer">
                                    + Input Alamat Baru
                                </button>
                                <span class="text-[9.5px] bg-[#ee4d2d] text-white font-black px-2 py-0.5 rounded-full uppercase tracking-wider">
                                    Wajib Diisi
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <!-- Baris 1: Nama Penerima & No HP -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10.5px] font-bold text-slate-700 mb-0.5">Nama Penerima Paket:</label>
                                    <input type="text" id="inputNamaPenerima" name="nama_penerima" value="<?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '') ?>" required placeholder="Nama lengkap penerima"
                                        class="w-full border border-slate-200 bg-white px-3 py-2 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                                </div>
                                <div>
                                    <label class="block text-[10.5px] font-bold text-slate-700 mb-0.5">No. HP / WhatsApp Penerima:</label>
                                    <input type="text" id="inputNoTelepon" name="no_telepon" value="<?= htmlspecialchars($telepon_profil_default) ?>" required placeholder="Contoh: 0812-3456-7890"
                                        class="w-full border border-slate-200 bg-white px-3 py-2 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                                </div>
                            </div>

                            <!-- Baris 2: Kota / Kabupaten -->
                            <div>
                                <label class="block text-[10.5px] font-bold text-slate-700 mb-0.5">Kota / Kabupaten & Provinsi Tujuan:</label>
                                <input type="text" id="inputKotaKirim" name="kota_pengiriman" value="<?= htmlspecialchars($kota_profil_default ?: 'Ponorogo') ?>" required placeholder="Contoh: Ponorogo, Jawa Timur"
                                    class="w-full border border-slate-200 bg-white px-3 py-2 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                            </div>

                            <!-- Baris 3: Alamat Lengkap -->
                            <div>
                                <label class="block text-[10.5px] font-bold text-slate-700 mb-0.5">Alamat Lengkap Pengiriman (Jalan, RT/RW, No. Rumah, Kelurahan, Kecamatan):</label>
                                <textarea id="inputAlamatKirim" name="alamat_pengiriman" rows="2" required placeholder="Tuliskan nama jalan, nomor rumah, RT/RW, dan patokan alamat Anda sendiri di sini..."
                                    class="w-full border border-slate-200 bg-white p-2.5 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d] leading-relaxed"><?= htmlspecialchars($alamat_profil_default) ?></textarea>
                            </div>

                            <!-- Baris 4: Catatan Kurir -->
                            <div>
                                <label class="block text-[10.5px] font-bold text-slate-700 mb-0.5">Catatan Khusus untuk Kurir (Opsional):</label>
                                <input type="text" id="inputCatatanKirim" name="catatan_pengiriman" placeholder="Contoh: Titipkan di pos satpam / Rumah pagar hitam"
                                    class="w-full border border-slate-200 bg-white px-3 py-2 rounded-xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                            </div>

                            <!-- Checkbox Simpan Profil & Reset -->
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-0.5">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="simpan_profil" value="1" checked class="w-3.5 h-3.5 text-[#ee4d2d] rounded border-slate-300 focus:ring-[#ee4d2d]">
                                    <span class="text-[10px] text-slate-600 font-medium select-none">Simpan alamat ini ke akun profil saya untuk pesanan berikutnya</span>
                                </label>
                                <?php if (!empty($alamat_profil_default)): ?>
                                <button type="button" onclick="muatAlamatTersimpan()" class="text-[10px] text-emerald-600 hover:text-emerald-800 font-extrabold hover:underline cursor-pointer">
                                    ↺ Muat Alamat Profil
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI PILIHAN METODE PEMBAYARAN -->
                    <div class="space-y-2 pt-1">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="wallet-cards" class="w-4 h-4 text-[#ee4d2d]"></i>
                                <span>2. Pilih Metode Pembayaran:</span>
                            </label>
                            <span class="text-[10px] text-emerald-600 font-extrabold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                Bebas Biaya Admin
                            </span>
                        </div>

                        <div>
                            <label for="selectMetodePembayaran" class="mb-1 block text-[11px] font-bold text-slate-600">Pilih kanal pembayaran:</label>
                            <select id="selectMetodePembayaran" name="metode_pembayaran" onchange="pilihMetodeBayar(this.value)"
                                class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                                <?php if ($metode_pembayaran_duitku): ?>
                                    <?php
                                    $grup_metode = '';
                                    foreach ($metode_pembayaran_duitku as $metode):
                                        if ($metode['group'] !== $grup_metode):
                                            if ($grup_metode !== '') echo '</optgroup>';
                                            $grup_metode = $metode['group'];
                                    ?>
                                            <optgroup label="<?= htmlspecialchars($grup_metode) ?> — Duitku">
                                        <?php endif; ?>
                                        <option value="<?= htmlspecialchars($metode['code']) ?>" data-group="<?= htmlspecialchars($metode['group']) ?>" <?= $metode_default_checkout === $metode['code'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($metode['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($grup_metode !== '') echo '</optgroup>'; ?>
                                <?php endif; ?>
                                <optgroup label="Pembayaran TokoKita">
                                    <option value="Saldo TokoKita Pay" <?= $metode_default_checkout === 'Saldo TokoKita Pay' ? 'selected' : '' ?>>
                                        Saldo TokoKita Pay (<?= htmlspecialchars(rupiah($saldo_pelanggan_login)) ?>)
                                    </option>
                                    <option value="COD (Bayar di Tempat)" <?= $metode_default_checkout === 'COD (Bayar di Tempat)' ? 'selected' : '' ?>>
                                        COD — Bayar di Tempat
                                    </option>
                                </optgroup>
                            </select>
                            <?php if (!$hasil_metode_pembayaran['status']): ?>
                                <p class="mt-1 text-[10px] font-semibold text-amber-700"><?= htmlspecialchars($hasil_metode_pembayaran['pesan']) ?> Saldo TokoKita dan COD tetap tersedia.</p>
                            <?php endif; ?>
                        </div>

                                        <!-- Kotak Informasi Detail Metode Bayar Terpilih -->
                                        <div id="boxInfoMetode" class="p-3 rounded-2xl border border-rose-200 bg-rose-50/50 flex items-start gap-2.5 text-[11px]">
                            <div class="w-6 h-6 rounded-lg bg-[#ee4d2d] text-white flex items-center justify-center shrink-0 mt-0.5">
                                <i data-lucide="info" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="space-y-0.5" id="kontenInfoMetode">
                                <p class="font-bold text-slate-800">QRIS Instan (BCA, DANA, GoPay, OVO, ShopeePay)</p>
                                <p class="text-slate-500 text-[10px]">Kode QRIS resmi akan otomatis di-generate dan dapat langsung di-scan tanpa jeda verifikasi manual.</p>
                            </div>
                        </div>
                        <p id="pesanBatasDuitku" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-700"></p>
                    </div>

                    <!-- Input Diskon Belanja -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600">Voucher / Kupon Diskon Belanja (%):</label>
                        <div class="relative">
                            <i data-lucide="percent" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                            <input type="number" id="inputDiskonKeranjang" name="diskon_persen" value="0" min="0" max="100" oninput="updateDiskonKeranjang()"
                                class="w-full pl-9 pr-4 py-2 border border-slate-200 bg-slate-50 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-[#ee4d2d] focus:bg-white transition">
                        </div>
                    </div>

                    <!-- Tombol Eksekusi Bayar Dinamis -->
                    <?php if ($ada_melebihi_stok): ?>
                        <button type="button" disabled
                            class="w-full bg-slate-200 text-slate-500 border border-slate-300 font-bold py-3.5 rounded-2xl cursor-not-allowed flex items-center justify-center gap-2 text-xs select-none">
                            <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                            <span>Checkout Terkunci (Kurangi Item Melebihi Stok)</span>
                        </button>
                    <?php else: ?>
                        <button type="submit" name="checkout_keranjang" id="btnSubmitBayar"
                            class="w-full bg-gradient-to-r from-[#ee4d2d] via-orange-500 to-rose-600 hover:opacity-95 text-white font-black py-3.5 rounded-2xl shadow-lg shadow-[#ee4d2d]/30 transition flex items-center justify-center gap-2 active:scale-[0.98] cursor-pointer text-xs">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            <span id="labelTombolBayar">Buat Pesanan & Bayar via QRIS Instan</span>
                        </button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        const totalAsli = <?= (float)$grand_total ?>;
        const saldoPelanggan = <?= (float)$saldo_pelanggan_login ?>;

        function formatRupiahJs(angka) {
            return 'Rp ' + Math.round(angka).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Fungsi Memilih Metode Pembayaran
        function pilihMetodeBayar(kodeMetode) {
            const select = document.getElementById('selectMetodePembayaran');
            const option = select ? select.options[select.selectedIndex] : null;
            const namaMetode = option ? option.textContent.trim() : kodeMetode;
            const namaMetodeHtml = namaMetode.replace(/[&<>"']/g, karakter => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[karakter]);
            const kelompok = option ? option.dataset.group : '';
            const infoBox = document.getElementById('kontenInfoMetode');
            const btnLabel = document.getElementById('labelTombolBayar');

            if (['NQ', 'SP'].includes(kodeMetode)) {
                infoBox.innerHTML = `
                    <p class="font-bold text-slate-800">${namaMetodeHtml}</p>
                    <p class="text-slate-500 text-[10px]">QRIS akan dibuat oleh Duitku setelah pesanan dikonfirmasi. Batas QRIS Rp 10.000.000 per transaksi.</p>
                `;
                btnLabel.innerText = "Buat Pesanan & Bayar via " + namaMetode;
            } else if (['OV', 'DN', 'LA', 'SA', 'DA'].includes(kodeMetode)) {
                infoBox.innerHTML = `
                    <p class="font-bold text-sky-700">${namaMetodeHtml}</p>
                    <p class="text-slate-500 text-[10px]">Anda akan diarahkan ke Duitku untuk menyelesaikan pembayaran melalui aplikasi atau layanan yang dipilih.</p>
                `;
                btnLabel.innerText = "Buat Pesanan & Bayar via " + namaMetode;
            } else if (kodeMetode === 'Saldo TokoKita Pay') {
                const cukup = saldoPelanggan >= totalAsli;
                infoBox.innerHTML = `
                    <p class="font-bold text-emerald-800">Saldo Dompet TokoKita Pay (${formatRupiahJs(saldoPelanggan)})</p>
                    <p class="text-[10px] ${cukup ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'}">
                        ${cukup ? '✓ Saldo Anda mencukupi untuk melunasi tagihan ini.' : '⚠ Saldo tidak cukup! Silakan lakukan isi saldo atau pilih metode lain.'}
                    </p>
                `;
                btnLabel.innerText = "Buat Pesanan & Bayar Pakai Saldo TokoKita (" + formatRupiahJs(totalAsli) + ")";
            } else if (kelompok === 'Virtual Account & Transfer Bank') {
                infoBox.innerHTML = `
                    <p class="font-bold text-indigo-900">${namaMetodeHtml}</p>
                    <p class="text-slate-500 text-[10px]">Nomor Virtual Account atau instruksi transfer resmi akan diberikan Duitku setelah pesanan dikonfirmasi.</p>
                `;
                btnLabel.innerText = "Buat Pesanan & Bayar via " + namaMetode;
            } else if (kelompok === 'Gerai') {
                infoBox.innerHTML = `
                    <p class="font-bold text-indigo-900">${namaMetodeHtml}</p>
                    <p class="text-slate-500 text-[10px]">Duitku akan menampilkan kode pembayaran dan petunjuk pembayaran di gerai.</p>
                `;
                btnLabel.innerText = "Buat Pesanan & Bayar via " + namaMetode;
            } else if (kelompok === 'Kartu Kredit') {
                infoBox.innerHTML = `
                    <p class="font-bold text-indigo-900">${namaMetodeHtml}</p>
                    <p class="text-slate-500 text-[10px]">Anda akan diarahkan ke halaman pembayaran aman Duitku untuk menyelesaikan transaksi.</p>
                `;
                btnLabel.innerText = "Buat Pesanan & Bayar via " + namaMetode;
            } else if (kodeMetode.includes('COD')) {
                infoBox.innerHTML = `
                    <p class="font-bold text-amber-800">COD (Bayar Tunai di Tempat Saat Kurir Tiba)</p>
                    <p class="text-slate-500 text-[10px]">Barang dikirim terlebih dahulu ke alamat Anda, siapkan uang tunai pas saat kurir mengantar pesanan.</p>
                `;
                btnLabel.innerText = "Buat Pesanan COD (Bayar di Tempat Saat Kurir Tiba)";
            }

            if (window.lucide) lucide.createIcons();
            updatePeringatanBatasDuitku();
        }

        // Update Kalkulasi Diskon Realtime
        function updateDiskonKeranjang() {
            const diskonInp = parseFloat(document.getElementById('inputDiskonKeranjang').value) || 0;
            const diskonPersen = Math.min(100, Math.max(0, diskonInp));
            const potongan = (totalAsli * diskonPersen) / 100;
            const totalAkhir = Math.max(0, totalAsli - potongan);

            const labelTotal = document.getElementById('labelGrandTotal');
            const labelKet = document.getElementById('labelKetDiskon');

            if (labelTotal) {
                labelTotal.innerText = formatRupiahJs(totalAkhir);
            }
            if (labelKet) {
                if (diskonPersen > 0) {
                    labelKet.classList.remove('hidden');
                    labelKet.innerText = `Hemat ${diskonPersen}% (-${formatRupiahJs(potongan)})`;
                } else {
                    labelKet.classList.add('hidden');
                }
            }
            updatePeringatanBatasDuitku();
        }

        function updatePeringatanBatasDuitku() {
            const selectedMethod = document.getElementById('selectMetodePembayaran');
            const diskon = Math.min(100, Math.max(0, parseFloat(document.getElementById('inputDiskonKeranjang').value) || 0));
            const total = Math.round(totalAsli - (totalAsli * diskon / 100));
            const pesanBatas = document.getElementById('pesanBatasDuitku');
            const qrisSelected = selectedMethod && ['NQ', 'SP'].includes(selectedMethod.value);
            if (pesanBatas) {
                pesanBatas.textContent = 'Tagihan melebihi batas QRIS Rp 10.000.000. Pilih Virtual Account BCA/BRI atau kurangi jumlah belanja.';
                pesanBatas.classList.toggle('hidden', !(qrisSelected && total > 10000000));
            }
        }

        function validasiCheckoutDuitku() {
            const selectedMethod = document.getElementById('selectMetodePembayaran');
            const diskon = Math.min(100, Math.max(0, parseFloat(document.getElementById('inputDiskonKeranjang').value) || 0));
            const total = Math.round(totalAsli - (totalAsli * diskon / 100));
            if (selectedMethod && ['NQ', 'SP'].includes(selectedMethod.value) && total > 10000000) {
                updatePeringatanBatasDuitku();
                document.getElementById('pesanBatasDuitku').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            return true;
        }

        function kosongkanAlamatKeranjang() {
            const inAlamat = document.getElementById('inputAlamatKirim');
            const inTelp = document.getElementById('inputNoTelepon');
            const inCatatan = document.getElementById('inputCatatanKirim');
            if (inAlamat) {
                inAlamat.value = '';
                inAlamat.placeholder = 'Tuliskan nama jalan, nomor rumah, RT/RW, dan patokan alamat Anda sendiri di sini...';
                inAlamat.focus();
            }
            if (inTelp) inTelp.value = '';
            if (inCatatan) inCatatan.value = '';
        }

        function muatAlamatTersimpan() {
            const inAlamat = document.getElementById('inputAlamatKirim');
            const inTelp = document.getElementById('inputNoTelepon');
            const inKota = document.getElementById('inputKotaKirim');
            if (inAlamat) inAlamat.value = <?= json_encode($alamat_profil_default) ?>;
            if (inTelp) inTelp.value = <?= json_encode($telepon_profil_default) ?>;
            if (inKota) inKota.value = <?= json_encode($kota_profil_default ?: 'Ponorogo') ?>;
        }

        function filterItemKeranjang() {
            const query = document.getElementById('cariItemKeranjang').value.toLowerCase();
            const cards = document.querySelectorAll('.kartu-item');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(query) ? '' : 'none';
            });
        }

        const metodeAwal = document.getElementById('selectMetodePembayaran');
        if (metodeAwal) pilihMetodeBayar(metodeAwal.value);
        updatePeringatanBatasDuitku();
    </script>
</body>
</html>
