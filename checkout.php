<?php
require_once 'functions.php';
require_once 'duitku_config.php';
cekLogin();

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

$sukses_trx   = null;
$error_trx    = "";
$metode_bayar = "Tunai";
$ref_barcode  = "";
$nominal_bayar_input = "";
$nominal_bayar = 0;
$kembalian = 0;
$hasil_metode_checkout = ambilMetodePembayaranDuitku(20000);
$metode_duitku_checkout = $hasil_metode_checkout['status'] ? $hasil_metode_checkout['methods'] : [];

if (isset($_POST['bayar_sekarang']) && !empty($_SESSION['keranjang'])) {
    // Kunci ID Pelanggan jika akun Pelanggan
    if (isPelanggan()) {
        $id_pelanggan = getIdPelangganLogin();
    } else {
        $id_pelanggan = (int)$_POST['id_pelanggan'];
    }

    $diskon            = (float)$_POST['diskon_persen'];
    $metode_bayar      = isset($_POST['metode_bayar']) ? mysqli_real_escape_string($conn, $_POST['metode_bayar']) : 'Tunai';
    $ref_barcode       = isset($_POST['ref_barcode']) ? mysqli_real_escape_string($conn, trim($_POST['ref_barcode'])) : '';
    $nominal_bayar_input = isset($_POST['nominal_bayar']) ? trim((string)$_POST['nominal_bayar']) : '';
    $alamat_pengiriman = isset($_POST['alamat_pengiriman']) ? mysqli_real_escape_string($conn, trim($_POST['alamat_pengiriman'])) : '';
    $no_telepon        = isset($_POST['no_telepon']) ? mysqli_real_escape_string($conn, trim($_POST['no_telepon'])) : '';
    $catatan_kirim     = isset($_POST['catatan_pengiriman']) ? mysqli_real_escape_string($conn, trim($_POST['catatan_pengiriman'])) : '';

    // VALIDASI KETAT STOK BARANG SEBELUM CHECKOUT
    $stok_aman = true;
    if (isPelanggan() && $id_pelanggan <= 0) {
        $error_trx = 'Akun pelanggan belum terhubung dengan data member. Hubungi kasir sebelum checkout.';
        $stok_aman = false;
    }
    if ($stok_aman) {
        foreach ($_SESSION['keranjang'] as $it) {
            $idp_chk = (int)$it['id_produk'];
            $jml_chk = (int)$it['jumlah'];
            $q_stk = mysqli_query($conn, "SELECT nama_produk, stok FROM tbl_produk WHERE id_produk = $idp_chk");
            $r_stk = mysqli_fetch_assoc($q_stk);

            if (!$r_stk) {
                $error_trx = "Produk '{$it['nama_produk']}' tidak ditemukan dalam katalog!";
                $stok_aman = false;
                break;
            }
            if ($r_stk['stok'] <= 0) {
                $error_trx = "Checkout gagal! Stok '{$r_stk['nama_produk']}' saat ini habis.";
                $stok_aman = false;
                break;
            }
            if ($jml_chk > $r_stk['stok']) {
                $error_trx = "Checkout ditolak! Jumlah pesanan untuk '{$it['nama_produk']}' ($jml_chk unit) melebihi stok yang tersedia ({$r_stk['stok']} unit).";
                $stok_aman = false;
                break;
            }
        }
    }

    if ($stok_aman) {
        $subtotal = 0;
        foreach ($_SESSION['keranjang'] as $it) {
            $subtotal += $it['subtotal'];
        }

        $q_hit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ($subtotal - HitungDiskon($subtotal, $diskon)) AS total_akhir"));
        $total_akhir = $q_hit['total_akhir'];

        // Validasi nominal tunai dan saldo sebelum membuat transaksi
        $pembayaran_aman = true;
        $is_tunai = (strtoupper(trim($metode_bayar)) === 'TUNAI');
        $is_gateway = isMetodeDuitku($metode_bayar);
        if (!$is_tunai && !$is_gateway) {
            $error_trx = "Metode pembayaran tidak valid. Pilih metode yang tersedia.";
            $pembayaran_aman = false;
        }

        if ($is_tunai) {
            $nominal_bayar = filter_var($nominal_bayar_input, FILTER_VALIDATE_INT);
            if ($nominal_bayar === false) {
                $error_trx = "Masukkan nominal pembayaran tunai dalam angka bulat.";
                $pembayaran_aman = false;
            } elseif ($nominal_bayar < $total_akhir) {
                $error_trx = "Nominal pembayaran kurang dari total tagihan " . rupiah($total_akhir) . ".";
                $pembayaran_aman = false;
            } else {
                $kembalian = $nominal_bayar - $total_akhir;
            }
        }

        if (strpos($metode_bayar, 'Saldo') !== false) {
            $q_sld = mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) AS total_saldo FROM tbl_topup WHERE id_pelanggan = $id_pelanggan AND status = 'Berhasil'");
            $r_sld = mysqli_fetch_assoc($q_sld);
            $saldo_ada = $r_sld ? (float)$r_sld['total_saldo'] : 0;
            if ($saldo_ada < $total_akhir) {
                $error_trx = "Saldo Dompet TokoKita Pay tidak mencukupi! Saldo: " . rupiah($saldo_ada) . ", Total Tagihan: " . rupiah($total_akhir);
                $pembayaran_aman = false;
            }
        }

        $pesan_batas_duitku = validasiBatasNominalDuitku($metode_bayar, $total_akhir);
        if ($pesan_batas_duitku !== '') {
            $error_trx = $pesan_batas_duitku;
            $pembayaran_aman = false;
        }
        if ($is_gateway && $pembayaran_aman) {
            if (!duitku_konfigurasi_siap()) {
                $error_trx = "Konfigurasi Duitku belum lengkap. Periksa DUITKU_MERCHANT_CODE dan DUITKU_API_KEY.";
                $pembayaran_aman = false;
            } else {
                $metode_aktif_checkout = ambilMetodePembayaranDuitku($total_akhir);
                if (empty($metode_aktif_checkout['status'])) {
                    $error_trx = $metode_aktif_checkout['pesan'] ?? "Gagal memeriksa metode pembayaran Duitku.";
                    $pembayaran_aman = false;
                } elseif (!isset($metode_aktif_checkout['methods'][$metode_bayar])) {
                    $error_trx = "Metode pembayaran " . namaMetodeDuitku($metode_bayar) . " tidak aktif untuk nominal transaksi ini.";
                    $pembayaran_aman = false;
                }
            }
        }

        if ($pembayaran_aman) {
            // Generate kode referensi jika belum terisi oleh scanner
            if (empty($ref_barcode)) {
                if (strpos($metode_bayar, 'QRIS') !== false) {
                    $ref_barcode = 'QRIS-' . date('Ymd') . '-' . rand(1000, 9999);
                } elseif (strpos($metode_bayar, 'Shopee') !== false || strpos($metode_bayar, 'SPay') !== false) {
                    $ref_barcode = 'SPL-' . date('Ymd') . '-' . rand(1000, 9999);
                } elseif (strpos($metode_bayar, 'DANA') !== false) {
                    $ref_barcode = 'DANA-' . date('Ymd') . '-' . rand(1000, 9999);
                } elseif (strpos($metode_bayar, 'Saldo') !== false) {
                    $ref_barcode = 'MBR-' . date('Ymd') . '-' . rand(1000, 9999);
                } elseif (strpos($metode_bayar, 'BCA') !== false) {
                    $ref_barcode = '89901' . rand(10000000, 99999999);
                } elseif (strpos($metode_bayar, 'BRI') !== false) {
                    $ref_barcode = '12800' . rand(10000000, 99999999);
                } elseif (strpos($metode_bayar, 'COD') !== false) {
                    $ref_barcode = 'COD-' . date('Ymd') . '-' . rand(1000, 9999);
                } else {
                    $ref_barcode = 'POS-' . date('Ymd') . '-' . rand(1000, 9999);
                }
            }

            $status_bayar = $is_gateway ? 'Pending' : ((strpos($metode_bayar, 'COD') !== false) ? 'Menunggu Pembayaran' : 'Lunas');

            mysqli_begin_transaction($conn);
            $ok = mysqli_query($conn, "INSERT INTO tbl_transaksi (tanggal, waktu_transaksi, id_pelanggan, total_belanja, metode_pembayaran, status_pembayaran, ref_pembayaran, alamat_pengiriman, no_telepon, catatan_pengiriman) VALUES (CURDATE(), NOW(), $id_pelanggan, $total_akhir, '$metode_bayar', '$status_bayar', '$ref_barcode', '$alamat_pengiriman', '$no_telepon', '$catatan_kirim')");
            $id_trx = mysqli_insert_id($conn);

            $pesan_err_item = "";
            foreach ($_SESSION['keranjang'] as $it) {
                $idp = $it['id_produk'];
                $nmp = mysqli_real_escape_string($conn, $it['nama_produk']);
                $jml = (int)$it['jumlah'];
                $hrg = (float)$it['harga'];
                if (!mysqli_query($conn, "INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES ($id_trx, $idp, '$nmp', $jml, $hrg)")) {
                    $ok = false;
                    $pesan_err_item = mysqli_error($conn);
                    break;
                }
            }

            // Jika metode bayar Saldo Dompet TokoKita, kurangi saldo
            if ($ok && strpos($metode_bayar, 'Saldo') !== false) {
                $ins_potong = mysqli_query($conn, "INSERT INTO tbl_topup (id_pelanggan, nominal, metode, status) VALUES ($id_pelanggan, -$total_akhir, 'Pembayaran Belanja #$id_trx', 'Berhasil')");
                if (!$ins_potong) {
                    $ok = false;
                    $pesan_err_item = "Gagal memotong saldo: " . mysqli_error($conn);
                }
            }

            if ($ok) {
    // Jika memilih pembayaran online Duitku
    if ($is_gateway) {
        if (!duitku_konfigurasi_siap()) {
            mysqli_rollback($conn);
            $error_trx = "Konfigurasi Duitku belum lengkap. Isi DUITKU_MERCHANT_CODE dan DUITKU_API_KEY.";
        } else {
        // Ambil nama pelanggan
        $r_pel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_pelanggan FROM tbl_pelanggan WHERE id_pelanggan = $id_pelanggan"));
        $nama_pembeli = $r_pel['nama_pelanggan'] ?? 'Pelanggan TokoKita';

        $order_id_duitku = "TRX-" . $id_trx;
        $duitku = buatTransaksiDuitku($order_id_duitku, $total_akhir, $metode_bayar, "Belanja TokoKita #$id_trx", $nama_pembeli);

        if ($duitku['status']) {
            $ref_url = mysqli_real_escape_string($conn, $duitku['paymentUrl']);
            $ref_id  = mysqli_real_escape_string($conn, $duitku['reference']);
            $update_status = mysqli_query($conn, "UPDATE tbl_transaksi SET status_pembayaran='Pending', ref_pembayaran='$ref_id', duitku_reference='$ref_id', duitku_url='$ref_url' WHERE id_transaksi=$id_trx");

            if ($update_status) {
                mysqli_commit($conn);
                $_SESSION['keranjang'] = [];
                header("Location: " . $duitku['paymentUrl']);
                exit;
            }
            mysqli_rollback($conn);
            $error_trx = "Gagal menyimpan data invoice Duitku.";
        } else {
            mysqli_rollback($conn);
            $error_trx = "Gagal membuat invoice Duitku: " . $duitku['pesan'];
        }
        }
    } else {
        mysqli_commit($conn);
        $_SESSION['keranjang'] = [];
        $sukses_trx = $id_trx;
    }
} else {
    mysqli_rollback($conn);
    $error_trx = "Checkout gagal: " . (!empty($pesan_err_item) ? $pesan_err_item : "terjadi kesalahan database.");
}
        }
    }
}

$q_pel = mysqli_query($conn, "SELECT * FROM view_pelanggan ORDER BY nama_pelanggan ASC");

// Auto-select ID Pelanggan jika login sebagai Pelanggan
$id_pel_login = 0;
$alamat_profil_default = '';
$telepon_profil_default = '';
if (isPelanggan()) {
    $nama_user = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);
    $qp = mysqli_query($conn, "SELECT * FROM tbl_pelanggan WHERE nama_pelanggan = '$nama_user' LIMIT 1");
    if ($rp = mysqli_fetch_assoc($qp)) {
        $id_pel_login = (int)$rp['id_pelanggan'];
        $alamat_profil_default = $rp['alamat'] ?? '';
        $telepon_profil_default = $rp['no_telepon'] ?? '';
    }
    if ($id_pel_login === 0) {
        $qp_any = mysqli_query($conn, "SELECT * FROM tbl_pelanggan LIMIT 1");
        if ($rp_any = mysqli_fetch_assoc($qp_any)) {
            $id_pel_login = (int)$rp_any['id_pelanggan'];
            $alamat_profil_default = $rp_any['alamat'] ?? '';
            $telepon_profil_default = $rp_any['no_telepon'] ?? '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Pembayaran - TokoKita</title>
    <!-- Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Library Barcode & QR Code Scanner HTML5 -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @keyframes scanLaser {
            0% { top: 4%; opacity: 0.8; }
            50% { top: 92%; opacity: 1; }
            100% { top: 4%; opacity: 0.8; }
        }
        .scanning-laser {
            position: absolute;
            left: 4%;
            width: 92%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #ef4444, #f43f5e, #ef4444, transparent);
            box-shadow: 0 0 14px 4px rgba(239, 68, 68, 0.85);
            animation: scanLaser 2s infinite ease-in-out;
            z-index: 20;
            pointer-events: none;
        }
        #readerCheckout video {
            border-radius: 1rem;
            object-fit: cover;
            width: 100% !important;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-4xl mx-auto p-4 md:p-6 space-y-6">
        
        <?php if ($sukses_trx): ?>
            <!-- Tampilan Pembayaran Berhasil -->
            <div class="bg-white p-8 md:p-10 rounded-3xl shadow-xl border border-emerald-200 text-center space-y-4 max-w-lg mx-auto">
                <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto shadow-lg shadow-emerald-500/20">
                    <i data-lucide="check-circle-2" class="w-10 h-10"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">Pembayaran Berhasil!</h2>
                <div class="flex items-center justify-center gap-2">
                    <span class="text-xs font-mono font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 py-1 px-3 rounded-full">
                        #TRX-<?= str_pad($sukses_trx, 4, '0', STR_PAD_LEFT) ?>
                    </span>
                    <span class="text-xs font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 py-1 px-3 rounded-full">
                        <?= htmlspecialchars($metode_bayar) ?>
                    </span>
                </div>
                <?php if (!empty($ref_barcode)): ?>
                    <p class="text-[11px] font-mono text-slate-500 bg-slate-50 p-2 rounded-xl border border-slate-200">
                        Ref Barcode: <?= htmlspecialchars($ref_barcode) ?>
                    </p>
                <?php endif; ?>
                <?php if (strtoupper(trim($metode_bayar)) === 'TUNAI'): ?>
                    <p class="text-xs font-semibold text-slate-600">
                        Tunai diterima: <span class="font-black text-slate-800"><?= rupiah($nominal_bayar) ?></span>
                        <span class="mx-1">·</span>
                        Kembalian: <span class="font-black text-emerald-700"><?= rupiah($kembalian) ?></span>
                    </p>
                <?php endif; ?>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    Transaksi telah di-COMMIT secara permanen ke MariaDB dan stok produk otomatis dipotong oleh Trigger Database.
                </p>
                <div class="flex flex-wrap justify-center gap-3 pt-3">
                    <a href="struk.php?id=<?= $sukses_trx ?>&metode=<?= urlencode($metode_bayar) ?>&bayar=<?= $total_akhir ?>" 
                       class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Cetak Struk Kasir 58mm
                    </a>
                    <?php if (isPelanggan()): ?>
                        <a href="kategori.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-2xl text-xs font-bold transition">
                            Belanja Lagi
                        </a>
                    <?php else: ?>
                        <a href="kasir_pos.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-2xl text-xs font-bold transition">
                            Transaksi Baru
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h1 class="text-lg font-extrabold text-slate-800 flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-5 h-5 text-indigo-600"></i> Konfirmasi Checkout Pembayaran
                        </h1>
                        <p class="text-xs text-slate-400 mt-0.5">Mendukung Tunai, QRIS, DANA, ShopeePay, OVO, dan Barcode Scanner</p>
                    </div>
                    <a href="keranjang.php" class="text-xs font-bold text-indigo-600 hover:underline">← Edit Keranjang</a>
                </div>

                <?php if ($error_trx): ?>
                    <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                        <span><?= $error_trx ?></span>
                    </div>
                <?php endif; ?>

                <?php if (empty($_SESSION['keranjang'])): ?>
                    <div class="text-center py-12 space-y-3">
                        <div class="w-14 h-14 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto">
                            <i data-lucide="shopping-cart" class="w-7 h-7"></i>
                        </div>
                        <h3 class="font-bold text-sm text-slate-700">Keranjang Belanja Kosong</h3>
                        <p class="text-xs text-slate-400">Silakan pilih produk terlebih dahulu sebelum melakukan checkout.</p>
                        <div class="pt-2">
                            <a href="<?= isPelanggan() ? 'kategori.php' : 'kasir_pos.php' ?>" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-xl text-xs inline-flex items-center gap-1.5 transition">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Pilih Produk Sekarang
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <!-- Tabel Rincian Belanja -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 uppercase tracking-wider text-[10px] border-b">
                                    <th class="p-3">Nama Produk</th>
                                    <th class="p-3 text-right">Harga Satuan</th>
                                    <th class="p-3 text-center">Kuantitas</th>
                                    <th class="p-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php $total = 0; foreach ($_SESSION['keranjang'] as $it): $total += $it['subtotal']; ?>
                                <tr>
                                    <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($it['nama_produk']) ?></td>
                                    <td class="p-3 text-right text-slate-500"><?= rupiah($it['harga']) ?></td>
                                    <td class="p-3 text-center font-bold text-slate-700"><?= $it['jumlah'] ?></td>
                                    <td class="p-3 text-right font-black text-emerald-600"><?= rupiah($it['subtotal']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Form Pembayaran & Scanner Barcode -->
                    <form method="POST" class="bg-slate-50 p-5 rounded-2xl border border-slate-200/70 space-y-4">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Kolom 1: Pelanggan -->
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Nama Pelanggan:</label>
                                <?php if (isPelanggan()): ?>
                                    <input type="hidden" name="id_pelanggan" value="<?= $id_pel_login ?>">
                                    <div class="bg-white border border-slate-200 p-2.5 rounded-xl text-xs font-bold text-indigo-900 flex items-center justify-between">
                                        <span><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                                        <span class="text-[9px] bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-md uppercase font-extrabold">Akun Pelanggan</span>
                                    </div>
                                <?php else: ?>
                                    <select name="id_pelanggan" required class="w-full border border-slate-300 bg-white p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <?php while ($p = mysqli_fetch_assoc($q_pel)): ?>
                                            <option value="<?= $p['id_pelanggan'] ?>">
                                                <?= htmlspecialchars($p['nama_pelanggan']) ?> (<?= htmlspecialchars($p['kota']) ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Kolom 2: Diskon Belanja -->
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Kupon Diskon Belanja (%):</label>
                                <input type="number" id="inputDiskonCheckout" name="diskon_persen" value="0" min="0" max="100" oninput="hitungTotalCheckout()"
                                    class="w-full border border-slate-300 bg-white p-2.5 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        <!-- Input Alamat Pengiriman Pesanan (Sebelum Membayar) -->
                        <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 space-y-3">
                            <div class="flex items-center justify-between border-b border-indigo-100/80 pb-2">
                                <span class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-indigo-600"></i> Alamat Pengiriman Pesanan
                                </span>
                                <span class="text-[9.5px] bg-indigo-100 text-indigo-700 font-bold px-2 py-0.5 rounded-full">
                                    Informasi Tujuan
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">No. Handphone / WA Penerima:</label>
                                    <input type="text" name="no_telepon" value="<?= htmlspecialchars(!empty($telepon_profil_default) ? $telepon_profil_default : '0812-3456-7890') ?>" placeholder="08xx-xxxx-xxxx"
                                        class="w-full border border-slate-300 bg-white p-2.5 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Catatan Pengiriman (Opsional):</label>
                                    <input type="text" name="catatan_pengiriman" placeholder="Contoh: Titipkan pos satpam / pagar hitam"
                                        class="w-full border border-slate-300 bg-white p-2.5 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Alamat Lengkap Pengiriman:</label>
                                <textarea name="alamat_pengiriman" rows="2" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan, dan kota tujuan..."
                                    class="w-full border border-slate-300 bg-white p-2.5 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"><?= htmlspecialchars(!empty($alamat_profil_default) ? $alamat_profil_default : 'Jl. Raya Ponorogo - Madiun No. 45, RT 02 / RW 01, Kel. Keniten, Ponorogo') ?></textarea>
                            </div>
                        </div>

                        <!-- Panel Scanner Barcode / QRIS Pembayaran -->
                        <div class="bg-white p-4 rounded-2xl border border-indigo-100 space-y-3 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                <span class="font-extrabold text-xs text-slate-800 flex items-center gap-1.5">
                                    <i data-lucide="wallet" class="w-4 h-4 text-indigo-600"></i> Metode Pembayaran:
                                </span>
                                <!-- Tombol Buka Scanner -->
                                <button type="button" onclick="bukaModalScannerCheckout()" 
                                    class="bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 flex items-center gap-1.5 transition active:scale-95 ring-2 ring-indigo-200">
                                    <i data-lucide="scan" class="w-4 h-4 text-amber-300"></i> Scan Barcode / QRIS Pembayaran
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Pilihan Cara Bayar:</label>
                                  <select id="selectMetodeCheckout" name="metode_bayar" class="w-full border border-slate-300 bg-slate-50 p-2.5 rounded-xl text-xs font-bold text-slate-800">
                                      <option value="TUNAI">Tunai (Cash Langsung di Kasir)</option>
                                      <?php if ($metode_duitku_checkout): ?>
                                          <?php
                                          $grup_checkout = '';
                                          foreach ($metode_duitku_checkout as $metode):
                                              if ($metode['group'] !== $grup_checkout):
                                                  if ($grup_checkout !== '') echo '</optgroup>';
                                                  $grup_checkout = $metode['group'];
                                          ?>
                                                  <optgroup label="<?= htmlspecialchars($grup_checkout) ?> — Duitku">
                                              <?php endif; ?>
                                              <option value="<?= htmlspecialchars($metode['code']) ?>"><?= htmlspecialchars($metode['name']) ?></option>
                                          <?php endforeach; ?>
                                          <?php if ($grup_checkout !== '') echo '</optgroup>'; ?>
                                      <?php endif; ?>
                                  </select>
                                  <?php if (!$hasil_metode_checkout['status']): ?>
                                      <p class="mt-1 text-[10px] font-semibold text-amber-700"><?= htmlspecialchars($hasil_metode_checkout['pesan']) ?> Tunai tetap tersedia.</p>
                                  <?php endif; ?>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Status Verifikasi Barcode:</label>
                                    <input type="hidden" id="hiddenRefBarcode" name="ref_barcode" value="">
                                    <div id="badgeBarcodeCheckout" class="bg-slate-100 text-slate-500 p-2 rounded-xl text-xs font-semibold flex items-center justify-between border border-slate-200">
                                        <span id="textBadgeBarcodeCheckout" class="flex items-center gap-1.5">
                                            <i data-lucide="info" class="w-3.5 h-3.5"></i> Siap di-scan atau pilih metode
                                        </span>
                                        <button type="button" id="btnResetBarcodeCheckout" onclick="resetBarcodeCheckout()" class="text-rose-600 font-bold hover:underline text-[11px] hidden">
                                            Reset
                                        </button>
                                    </div>
                                </div>
                                <div id="panelNominalTunaiCheckout" class="hidden">
                                    <label for="inputNominalBayarCheckout" class="block text-[11px] font-bold text-slate-600 mb-1">Nominal Uang Tunai:</label>
                                    <input type="number" id="inputNominalBayarCheckout" name="nominal_bayar" min="0" step="1"
                                        value="<?= htmlspecialchars($nominal_bayar_input, ENT_QUOTES, 'UTF-8') ?>"
                                        oninput="hitungKembalianCheckout()"
                                        class="w-full border border-slate-300 bg-slate-50 p-2.5 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        placeholder="Masukkan uang yang dibayarkan">
                                    <p id="labelKembalianCheckout" class="text-[11px] font-semibold text-slate-500 mt-1">Masukkan nominal uang yang dibayarkan.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Baris Total & Tombol Bayar -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <div>
                                <span class="text-xs text-slate-500 font-medium block">Total Pembayaran:</span>
                                <span id="labelTotalAkhirCheckout" class="text-xl font-black text-emerald-600"><?= rupiah($total) ?></span>
                            </div>

                            <button type="submit" name="bayar_sekarang" id="btnSubmitBayar"
                                class="bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold px-6 py-3 rounded-2xl shadow-lg shadow-emerald-600/30 text-xs transition flex items-center justify-center gap-2 active:scale-[0.98]">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i> Konfirmasi & Bayar Sekarang
                            </button>
                        </div>

                    </form>

                <?php endif; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- ======================================================== -->
    <!-- MODAL SCANNER BARCODE & QR PEMBAYARAN CHECKOUT           -->
    <!-- Mendukung: Kamera Webcam/HP, USB Barcode Gun, Simulasi   -->
    <!-- ======================================================== -->
    <div id="modalScannerCheckout" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all duration-200">
            
            <!-- Header Modal -->
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-2xl bg-indigo-600/30 border border-indigo-400/40 flex items-center justify-center text-indigo-400 shadow-inner">
                        <i data-lucide="scan" class="w-5 h-5 text-indigo-400 animate-pulse"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-white flex items-center gap-2">
                            Scanner Barcode & QR Pembayaran
                            <span class="bg-emerald-500/20 text-emerald-400 text-[9px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/30">ONLINE</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Scan QRIS, DANA, ShopeePay, OVO, atau Kartu Pelanggan</p>
                    </div>
                </div>
                <button type="button" onclick="tutupModalScannerCheckout()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Body Modal -->
            <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto">
                
                <!-- Tab Switcher -->
                <div class="flex items-center bg-slate-100 p-1 rounded-2xl text-xs font-bold">
                    <button type="button" id="tabKameraCheckoutBtn" onclick="pilihTabScannerCheckout('kamera')" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm">
                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Kamera Live
                    </button>
                    <button type="button" id="tabGunCheckoutBtn" onclick="pilihTabScannerCheckout('gun')" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                        <i data-lucide="barcode" class="w-3.5 h-3.5"></i> Barcode Gun USB
                    </button>
                    <button type="button" id="tabSimCheckoutBtn" onclick="pilihTabScannerCheckout('sim')" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i> Uji Cepat Demo
                    </button>
                </div>

                <!-- KONTEN 1: KAMERA SCANNER LIVE -->
                <div id="scannerContentKameraCheckout" class="space-y-3">
                    <div class="relative bg-slate-900 rounded-2xl overflow-hidden aspect-video max-h-[250px] flex items-center justify-center border-2 border-slate-700 shadow-inner">
                        <div id="readerCheckout" class="w-full h-full flex items-center justify-center"></div>
                        <div id="scanLaserLineCheckout" class="scanning-laser"></div>

                        <div id="cameraPlaceholderCheckout" class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 bg-slate-900/90 z-10">
                            <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                                <i data-lucide="camera" class="w-6 h-6"></i>
                            </div>
                            <p class="text-xs font-bold text-white mb-1">Kamera Scanner Siap Digunakan</p>
                            <p class="text-[11px] text-slate-400 mb-3 max-w-xs">Arahkan barcode pelanggan atau QRIS ke kamera komputer/HP</p>
                            <button type="button" onclick="startCameraScannerCheckout()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-1.5 transition">
                                <i data-lucide="video" class="w-3.5 h-3.5"></i> Nyalakan Kamera
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            <span>Arahkan kode QR / Barcode tepat ke dalam kotak kamera</span>
                        </div>
                        <button type="button" id="btnToggleCamCheckout" onclick="stopCameraScannerCheckout()" class="text-xs font-bold text-rose-600 hover:text-rose-700 px-2 py-1 rounded-lg hover:bg-rose-50 transition hidden">
                            Matikan Kamera
                        </button>
                    </div>
                </div>

                <!-- KONTEN 2: BARCODE GUN USB INPUT -->
                <div id="scannerContentGunCheckout" class="hidden space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center">
                            <i data-lucide="scan-barcode" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-slate-800">Scanner Fisik Barcode Gun USB</h4>
                            <p class="text-[11px] text-slate-500">Arahkan dan tembak barcode dengan alat scanner fisik</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Input Barcode Gun (Auto-Enter):</label>
                        <div class="relative">
                            <i data-lucide="barcode" class="w-4 h-4 text-slate-400 absolute left-3 top-3"></i>
                            <input type="text" id="inputScannerGunCheckout" placeholder="Arahkan barcode gun lalu tembak di sini..."
                                class="w-full pl-10 pr-20 py-2.5 bg-white border-2 border-indigo-300 rounded-xl text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm"
                                autocomplete="off">
                            <button type="button" onclick="submitGunScanCheckout()" class="absolute right-2 top-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold px-3 py-1 rounded-lg transition">
                                Proses
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KONTEN 3: PENGUJIAN CEPAT / DEMO SIMULASI -->
                <div id="scannerContentSimCheckout" class="hidden space-y-3">
                    <div class="bg-indigo-50/70 border border-indigo-200 p-3 rounded-2xl">
                        <p class="text-xs font-bold text-indigo-900 mb-1 flex items-center gap-1.5">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i> Pengujian 1-Klik Simulasi:
                        </p>
                        <p class="text-[11px] text-indigo-700">Klik salah satu metode pembayaran di bawah untuk mensimulasikan hasil scan:</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <button type="button" onclick="simulasiScanCheckout('QRIS-NMID-00020101021226590014ID-BCA-88902')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                QR
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-indigo-600">QRIS BCA / Nasional</p>
                                <p class="text-[10px] font-mono text-slate-400">NMID: ID-BCA-88902</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScanCheckout('DANA-PAY-081234567890-REF-992384')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                DN
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-blue-600">DANA Dompet Digital</p>
                                <p class="text-[10px] font-mono text-slate-400">DANA-081234567890</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScanCheckout('SHOPEEPAY-SPAY-TRX-7749201948')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-amber-500 hover:bg-amber-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                SP
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-amber-600">ShopeePay QR</p>
                                <p class="text-[10px] font-mono text-slate-400">SPAY-TRX-774920</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScanCheckout('OVO-CASH-PAY-8839102934')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-purple-500 hover:bg-purple-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                OV
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-purple-600">OVO Payment</p>
                                <p class="text-[10px] font-mono text-slate-400">OVO-8839102934</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScanCheckout('MBR-TK-009482-SALDO-TOKOKITA')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm col-span-full">
                            <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                TK
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-emerald-600">Kartu Member TokoKita Pay (Saldo)</p>
                                <p class="text-[10px] font-mono text-slate-400">Barcode ID: MBR-TK-009482</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Footer Info -->
                <div class="bg-slate-50 p-3 rounded-2xl text-[11px] text-slate-500 flex items-center justify-between border border-slate-100">
                    <span class="flex items-center gap-1.5 font-medium">
                        <i data-lucide="volume-2" class="w-3.5 h-3.5 text-indigo-600"></i> Suara Beep Retail Aktif
                    </span>
                    <span class="font-semibold text-slate-700">Scan Barcode untuk Memilih Metode Bayar</span>
                </div>

            </div>

        </div>
    </div>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();
        const totalKatalog = <?= $total ?? 0 ?>;

        function hitungTotalCheckout() {
            const diskon = parseFloat(document.getElementById('inputDiskonCheckout').value) || 0;
            const potongan = totalKatalog * (diskon / 100);
            const akhir = Math.max(0, totalKatalog - potongan);
            document.getElementById('labelTotalAkhirCheckout').innerText = formatRupiah(akhir);
            hitungKembalianCheckout();
        }

        function toggleInputNominalCheckout() {
            const panel = document.getElementById('panelNominalTunaiCheckout');
            const input = document.getElementById('inputNominalBayarCheckout');
            const isTunai = document.getElementById('selectMetodeCheckout').value === 'TUNAI';
            panel.classList.toggle('hidden', !isTunai);
            input.required = isTunai;
            hitungKembalianCheckout();
        }

        function hitungKembalianCheckout() {
            const input = document.getElementById('inputNominalBayarCheckout');
            const label = document.getElementById('labelKembalianCheckout');
            if (!input || !label) return;

            const diskon = parseFloat(document.getElementById('inputDiskonCheckout').value) || 0;
            const total = Math.max(0, totalKatalog - (totalKatalog * diskon / 100));
            const dibayar = parseFloat(input.value);

            if (!Number.isFinite(dibayar) || input.value === '') {
                label.innerText = 'Masukkan nominal uang yang dibayarkan.';
                label.className = 'text-[11px] font-semibold text-slate-500 mt-1';
            } else if (dibayar < total) {
                label.innerText = 'Nominal kurang ' + formatRupiah(total - dibayar);
                label.className = 'text-[11px] font-semibold text-rose-600 mt-1';
            } else {
                label.innerText = 'Kembalian: ' + formatRupiah(dibayar - total);
                label.className = 'text-[11px] font-semibold text-emerald-700 mt-1';
            }
        }

        const selectMetodeCheckout = document.getElementById('selectMetodeCheckout');
        if (selectMetodeCheckout) {
            selectMetodeCheckout.addEventListener('change', toggleInputNominalCheckout);
            toggleInputNominalCheckout();
        }

        // ============================================
        // LOGIKA SCANNER BARCODE DI CHECKOUT PEMBAYARAN
        // ============================================
        let html5QrCheckout = null;
        let isCamCheckoutRunning = false;

        function bukaModalScannerCheckout() {
            const modal = document.getElementById('modalScannerCheckout');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                pilihTabScannerCheckout('kamera');
                refreshIcons();
            }
        }

        function tutupModalScannerCheckout() {
            stopCameraScannerCheckout();
            const modal = document.getElementById('modalScannerCheckout');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function pilihTabScannerCheckout(tab) {
            const btnKam = document.getElementById('tabKameraCheckoutBtn');
            const btnGun = document.getElementById('tabGunCheckoutBtn');
            const btnSim = document.getElementById('tabSimCheckoutBtn');

            const conKam = document.getElementById('scannerContentKameraCheckout');
            const conGun = document.getElementById('scannerContentGunCheckout');
            const conSim = document.getElementById('scannerContentSimCheckout');

            [btnKam, btnGun, btnSim].forEach(b => {
                b.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900';
            });
            [conKam, conGun, conSim].forEach(c => c.classList.add('hidden'));

            if (tab === 'kamera') {
                btnKam.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm';
                conKam.classList.remove('hidden');
            } else if (tab === 'gun') {
                stopCameraScannerCheckout();
                btnGun.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm';
                conGun.classList.remove('hidden');
                setTimeout(() => {
                    const gunInp = document.getElementById('inputScannerGunCheckout');
                    if (gunInp) gunInp.focus();
                }, 100);
            } else if (tab === 'sim') {
                stopCameraScannerCheckout();
                btnSim.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm';
                conSim.classList.remove('hidden');
            }
            refreshIcons();
        }

        function startCameraScannerCheckout() {
            if (typeof Html5Qrcode === 'undefined') {
                alert('Library Barcode Scanner sedang dimuat, mohon tunggu sebentar...');
                return;
            }

            document.getElementById('cameraPlaceholderCheckout').classList.add('hidden');
            document.getElementById('btnToggleCamCheckout').classList.remove('hidden');

            if (!html5QrCheckout) {
                html5QrCheckout = new Html5Qrcode("readerCheckout");
            }

            const config = { fps: 15, qrbox: { width: 220, height: 220 } };

            html5QrCheckout.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    prosesHasilScanCheckout(decodedText);
                },
                (errorMessage) => {}
            ).then(() => {
                isCamCheckoutRunning = true;
            }).catch(err => {
                console.warn("Kamera scanner gagal:", err);
                document.getElementById('cameraPlaceholderCheckout').classList.remove('hidden');
                document.getElementById('btnToggleCamCheckout').classList.add('hidden');
                alert("Tidak dapat mengakses kamera. Silakan gunakan Tab Barcode Gun atau Uji Cepat Demo.");
            });
        }

        function stopCameraScannerCheckout() {
            if (html5QrCheckout && isCamCheckoutRunning) {
                html5QrCheckout.stop().then(() => {
                    isCamCheckoutRunning = false;
                    document.getElementById('cameraPlaceholderCheckout').classList.remove('hidden');
                    document.getElementById('btnToggleCamCheckout').classList.add('hidden');
                }).catch(err => console.error(err));
            }
        }

        function prosesHasilScanCheckout(rawCode) {
            if (!rawCode) return;

            // Suara beep retail
            playScannerBeep();

            // Analisis payload barcode
            const parsed = parsePaymentBarcode(rawCode);

            // Set metode di form
            const select = document.getElementById('selectMetodeCheckout');
            if (select) {
                const metodeDuitku = {
                    'DANA': 'DA',
                    'ShopeePay': 'SP',
                    'OVO': 'OV',
                    'Transfer BCA': 'BC',
                    'Transfer BRI': 'BR',
                    'QRIS': 'NQ'
                };
                select.value = metodeDuitku[parsed.metode] || 'NQ';
            }

            const hiddenRef = document.getElementById('hiddenRefBarcode');
            if (hiddenRef) hiddenRef.value = parsed.raw;

            // Update badge tampilan
            const badge = document.getElementById('badgeBarcodeCheckout');
            const textBadge = document.getElementById('textBadgeBarcodeCheckout');
            const btnReset = document.getElementById('btnResetBarcodeCheckout');

            if (badge && textBadge) {
                badge.className = 'bg-emerald-50 text-emerald-800 p-2 rounded-xl text-xs font-bold flex items-center justify-between border border-emerald-300';
                textBadge.innerHTML = '<span class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i> Terverifikasi: ' + parsed.label + ' (' + parsed.refCode + ')</span>';
                if (btnReset) btnReset.classList.remove('hidden');
            }

            tutupModalScannerCheckout();
            tampilkanToast("✅ Barcode Pembayaran Diterima: " + parsed.label, "success");
            refreshIcons();
        }

        function resetBarcodeCheckout() {
            const select = document.getElementById('selectMetodeCheckout');
            if (select) select.value = 'TUNAI';

            const hiddenRef = document.getElementById('hiddenRefBarcode');
            if (hiddenRef) hiddenRef.value = '';

            const badge = document.getElementById('badgeBarcodeCheckout');
            const textBadge = document.getElementById('textBadgeBarcodeCheckout');
            const btnReset = document.getElementById('btnResetBarcodeCheckout');

            if (badge && textBadge) {
                badge.className = 'bg-slate-100 text-slate-500 p-2 rounded-xl text-xs font-semibold flex items-center justify-between border border-slate-200';
                textBadge.innerHTML = '<span class="flex items-center gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5"></i> Siap di-scan atau pilih metode</span>';
                if (btnReset) btnReset.classList.add('hidden');
            }

            tampilkanToast("Status barcode pembayaran di-reset ke Tunai.", "info");
            refreshIcons();
        }

        function simulasiScanCheckout(code) {
            prosesHasilScanCheckout(code);
        }

        function submitGunScanCheckout() {
            const inp = document.getElementById('inputScannerGunCheckout');
            if (inp && inp.value.trim() !== '') {
                prosesHasilScanCheckout(inp.value.trim());
                inp.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const inp = document.getElementById('inputScannerGunCheckout');
            if (inp) {
                inp.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitGunScanCheckout();
                    }
                });
            }
        });
    </script>
</body>
</html>