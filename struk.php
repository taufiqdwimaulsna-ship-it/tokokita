<?php
/**
 * TokoKita - Cetak Struk Kasir Model Thermal POS
 * File: struk.php
 * Format: Menyesuaikan 100% pas dengan lebar dan tinggi kertas printer thermal kasir (EPSON TM-T82X, POS-58, POS-80) tanpa sisa kosong.
 * Dilengkapi input jumlah uang bayar, pilihan metode bayar (Tunai, DANA, QRIS, dll.), dan kalkulasi kembalian otomatis di struk.
 */
require_once 'functions.php';
cekLogin();

// Pembatasan Hak Akses: Pelanggan TIDAK BISA mencetak struk kasir! Hak cetak struk khusus Kasir POS & Administrator.
if (isPelanggan()) {
    $_SESSION['pesan_akses'] = "Akses Ditolak: Pelanggan tidak memiliki izin untuk mencetak struk kasir! Fitur cetak struk thermal khusus Kasir POS dan Admin.";
    header("Location: index.php");
    exit;
}

// Mode Tampilan: 'tokokita' (data database riil) atau 'preset' (contoh persis seperti foto)
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'tokokita';

// Pilihan Lebar Preview di Layar: '58' (58mm), '80' (80mm EPSON), 'fit' (Pas Penuh)
$lebar = isset($_GET['lebar']) ? $_GET['lebar'] : 'fit';

// Ambil ID Transaksi dari parameter GET
$id_transaksi = 0;
if (isset($_GET['id'])) {
    $id_transaksi = (int)$_GET['id'];
} elseif (isset($_GET['struk'])) {
    $id_transaksi = (int)$_GET['struk'];
} elseif (isset($_GET['id_transaksi'])) {
    $id_transaksi = (int)$_GET['id_transaksi'];
}

// Jika tidak diberikan parameter, ambil transaksi terakhir
if ($id_transaksi === 0) {
    $q_last = mysqli_query($conn, "SELECT id_transaksi FROM tbl_transaksi ORDER BY id_transaksi DESC LIMIT 1");
    if ($row_last = mysqli_fetch_assoc($q_last)) {
        $id_transaksi = (int)$row_last['id_transaksi'];
    }
}

// Ambil Header Transaksi
$q_trx = mysqli_query($conn, "
    SELECT t.*, p.nama_pelanggan, p.kota, KategoriBelanja(t.total_belanja) AS level_pelanggan
    FROM tbl_transaksi t
    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_transaksi = $id_transaksi
");
$data_trx = mysqli_fetch_assoc($q_trx);

// Ambil Detail Barang yang dibeli
$item_belanja = [];
if ($data_trx) {
    $q_detail = mysqli_query($conn, "
        SELECT *, HitungTotal(harga, jumlah) AS subtotal_item
        FROM tbl_detail_transaksi
        WHERE id_transaksi = $id_transaksi
    ");
    $item_belanja = mysqli_fetch_all($q_detail, MYSQLI_ASSOC);
}

// Tampilkan waktu transaksi aktual; transaksi lama mungkin belum memiliki jam tercatat.
$waktu_struk = !empty($data_trx['waktu_transaksi'])
    ? date('d.m.Y/H:i:s', strtotime($data_trx['waktu_transaksi']))
    : date('d.m.Y', strtotime($data_trx ? $data_trx['tanggal'] : date('Y-m-d'))) . '/Jam tidak tercatat';

// Kasir Aktif
$nama_kasir = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Ursula';

// Hitung total belanja
$total_belanja = $data_trx ? (float)$data_trx['total_belanja'] : 0;
$ppn_nominal = (float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT HitungPajak($total_belanja) AS ppn"))['ppn'];

// Parameter Pembayaran & Kembalian
$metode_bayar = isset($_GET['metode']) ? $_GET['metode'] : ($data_trx && !empty($data_trx['metode_pembayaran']) ? $data_trx['metode_pembayaran'] : 'Tunai');
$uang_bayar = isset($_GET['bayar']) ? (float)$_GET['bayar'] : 0;

if ($mode === 'preset') {
    $total_ref = 54.50;
    if ($uang_bayar <= 0) $uang_bayar = 60.00;
} else {
    $total_ref = $total_belanja;
    if ($uang_bayar <= 0) {
        $uang_bayar = $total_belanja > 0 ? (ceil($total_belanja / 50000) * 50000) : 0;
        if ($uang_bayar < $total_belanja) $uang_bayar = $total_belanja;
    }
}
$uang_kembali = max(0, $uang_bayar - $total_ref);

// Daftar transaksi untuk navigasi cepat
$q_all_trx = mysqli_query($conn, "
    SELECT t.id_transaksi, t.tanggal, p.nama_pelanggan 
    FROM tbl_transaksi t 
    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan 
    ORDER BY t.id_transaksi DESC LIMIT 25
");

// Kelas lebar preview di layar
$width_class = "max-w-[420px]";
$print_page_width = '80mm';
$print_receipt_width = '72mm';
if ($lebar === '58') {
    $width_class = "max-w-[260px] text-[10px]";
    $print_page_width = '58mm';
    $print_receipt_width = '48mm';
} elseif ($lebar === '80') {
    $width_class = "max-w-[340px] text-xs";
} else {
    $width_class = "max-w-[380px] text-xs";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Kasir Thermal - #TRX-<?= $id_transaksi ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=VT323&display=swap');

        .font-receipt {
            font-family: 'Courier Prime', 'Lucida Console', 'Courier New', Courier, monospace;
            letter-spacing: -0.2px;
        }

        .wood-desk {
            background-color: #b87e4f;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(255,255,255,0.06) 0%, transparent 80%),
                linear-gradient(90deg, rgba(0,0,0,0.08) 0%, transparent 20%, rgba(0,0,0,0.05) 50%, transparent 80%, rgba(0,0,0,0.08) 100%),
                repeating-linear-gradient(0deg, #b87e4f, #b87e4f 3px, #ae7445 4px, #ae7445 6px);
        }

        .paper-receipt {
            background-color: #ffffff;
            color: #000000;
            box-shadow: 
                0 1px 3px rgba(0,0,0,0.15),
                0 10px 25px rgba(0,0,0,0.25),
                0 20px 40px -10px rgba(0,0,0,0.35);
            border-radius: 2px;
        }

        /* HAPUS SEMUA MARGIN DAN RUANG KOSONG BERLEBIH SAAT CETAK */
        @page {
            size: <?= $print_page_width ?> auto;
            margin: 0;
        }

        @media print {
            html, body {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
                height: auto !important;
                min-height: 0 !important;
                max-height: max-content !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                overflow: visible !important;
            }

            .no-print {
                display: none !important;
            }

            .wood-desk {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: 0 !important;
                height: auto !important;
                display: block !important;
            }

            .paper-receipt {
                width: <?= $print_receipt_width ?> !important;
                max-width: <?= $print_receipt_width ?> !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
                height: auto !important;
                min-height: 0 !important;
                max-height: max-content !important;
                margin: 0 auto !important;
                padding: 2mm 0 4mm !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                font-size: 11px !important;
                line-height: 1.25 !important;
            }
        }
    </style>
</head>
<body class="wood-desk min-h-screen text-slate-800 font-sans p-2 sm:p-4 md:p-6 flex flex-col items-center justify-start">

    <!-- Navigasi & Kontrol Atas (Tidak ikut tercetak) -->
    <div class="no-print w-full max-w-7xl mb-4 space-y-3">
        <?php include 'navbar.php'; ?>

        <!-- Toolbar Atas -->
        <div class="bg-slate-900/90 text-white backdrop-blur-md p-3 sm:p-4 rounded-2xl shadow-xl border border-slate-700 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <a href="kasir_pos.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Kasir
                </a>
                <a href="kasir_riwayat.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                    <i data-lucide="history" class="w-4 h-4"></i> Riwayat
                </a>
            </div>

            <!-- Pengaturan Lebar Preview Layar -->
            <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl text-xs font-bold">
                <a href="struk.php?id=<?= $id_transaksi ?>&mode=<?= $mode ?>&lebar=58&bayar=<?= $uang_bayar ?>&metode=<?= urlencode($metode_bayar) ?>" class="px-2 py-1 rounded-lg transition <?= $lebar === '58' ? 'bg-amber-600 text-white' : 'text-slate-300 hover:text-white' ?>" title="Kertas 58 mm">
                    58mm
                </a>
                <a href="struk.php?id=<?= $id_transaksi ?>&mode=<?= $mode ?>&lebar=80&bayar=<?= $uang_bayar ?>&metode=<?= urlencode($metode_bayar) ?>" class="px-2 py-1 rounded-lg transition <?= $lebar === '80' ? 'bg-amber-600 text-white' : 'text-slate-300 hover:text-white' ?>" title="Kertas 80 mm (EPSON TM-T82X)">
                    80mm
                </a>
                <a href="struk.php?id=<?= $id_transaksi ?>&mode=<?= $mode ?>&lebar=fit&bayar=<?= $uang_bayar ?>&metode=<?= urlencode($metode_bayar) ?>" class="px-2 py-1 rounded-lg transition <?= $lebar === 'fit' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' ?>" title="Pas Penuh">
                    Auto Fit
                </a>
            </div>

            <!-- Tombol Cetak -->
            <div>
                <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-xl text-xs font-bold shadow flex items-center gap-1.5 transition">
                    <i data-lucide="printer" class="w-4 h-4"></i> Cetak Struk
                </button>
            </div>
        </div>

        <!-- PANEL INPUT PEMBAYARAN KASIR & KEMBALIAN INTERAKTIF -->
        <div class="bg-white p-4 rounded-2xl shadow-xl border border-slate-200 space-y-3">
            <div class="flex flex-wrap items-center justify-between border-b border-slate-100 pb-2.5 gap-2">
                <div class="flex items-center gap-2">
                    <div class="bg-indigo-600 p-1.5 rounded-lg text-white">
                        <i data-lucide="calculator" class="w-4 h-4"></i>
                    </div>
                    <span class="font-extrabold text-xs uppercase tracking-wider text-slate-800">
                        Input Pembayaran Kasir & Kembalian
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-slate-500">Total Belanja:</span>
                    <span class="text-xs font-black text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-lg">
                        <?= $mode === 'preset' ? 'CHF 54.50' : rupiah($total_belanja) ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1 flex items-center gap-1">
                        <i data-lucide="wallet" class="w-3.5 h-3.5 text-indigo-600"></i> Bayar Menggunakan:
                    </label>
                    <select id="selectMetode" onchange="onMetodeChange()" class="w-full border border-slate-300 bg-slate-50 p-2.5 rounded-xl font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Tunai" <?= $metode_bayar === 'Tunai' ? 'selected' : '' ?>>Tunai (Cash)</option>
                        <option value="DANA" <?= $metode_bayar === 'DANA' ? 'selected' : '' ?>>DANA Dompet Digital</option>
                        <option value="QRIS" <?= $metode_bayar === 'QRIS' ? 'selected' : '' ?>>QRIS (Gopay/OVO/Dana/BCA)</option>
                        <option value="ShopeePay" <?= $metode_bayar === 'ShopeePay' ? 'selected' : '' ?>>ShopeePay</option>
                        <option value="OVO" <?= $metode_bayar === 'OVO' ? 'selected' : '' ?>>OVO Payment</option>
                        <option value="Transfer BCA" <?= $metode_bayar === 'Transfer BCA' ? 'selected' : '' ?>>Transfer Virtual Account BCA</option>
                        <option value="Transfer BRI" <?= $metode_bayar === 'Transfer BRI' ? 'selected' : '' ?>>Transfer Virtual Account BRI</option>
                        <option value="Saldo TokoKita" <?= $metode_bayar === 'Saldo TokoKita' ? 'selected' : '' ?>>Saldo Dompet TokoKita Pay</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1 flex items-center gap-1">
                        <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i> Jumlah Uang Diterima:
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 font-bold text-slate-400 text-xs"><?= $mode === 'preset' ? 'CHF' : 'Rp' ?></span>
                        <input type="number" id="inputBayar" oninput="updateBayarKembalian()" value="<?= $uang_bayar ?>" step="<?= $mode === 'preset' ? '0.5' : '1000' ?>"
                            class="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-xl font-bold text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    </div>
                </div>
            </div>

            <!-- Tombol Preset Cepat Pembayaran -->
            <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-slate-100">
                <span class="text-[11px] text-slate-500 font-bold mr-1">Preset Cepat:</span>
                <button type="button" onclick="setUangPas()" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-[11px] font-bold border border-indigo-200 transition">
                    Uang Pas
                </button>
                <?php if ($mode === 'preset'): ?>
                    <button type="button" onclick="setNominalBayar(55)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">CHF 55</button>
                    <button type="button" onclick="setNominalBayar(60)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">CHF 60</button>
                    <button type="button" onclick="setNominalBayar(100)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">CHF 100</button>
                <?php else: ?>
                    <button type="button" onclick="setNominalBayar(50000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">50 Rb</button>
                    <button type="button" onclick="setNominalBayar(100000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">100 Rb</button>
                    <button type="button" onclick="setNominalBayar(200000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">200 Rb</button>
                    <button type="button" onclick="setNominalBayar(300000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">300 Rb</button>
                    <button type="button" onclick="setNominalBayar(500000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold">500 Rb</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bar Mode Transaksi -->
        <div class="bg-amber-500/10 border border-amber-500/30 text-amber-900 p-2.5 rounded-2xl flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-1.5 font-bold">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>Metode Pembayaran & Kembalian Tercatat Langsung di Struk</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="struk.php?id=<?= $id_transaksi ?>&mode=tokokita&lebar=<?= $lebar ?>" class="px-2.5 py-0.5 rounded-lg text-xs font-bold transition <?= $mode === 'tokokita' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700' ?>">
                    Data TokoKita
                </a>
                <a href="struk.php?id=<?= $id_transaksi ?>&mode=preset&lebar=<?= $lebar ?>" class="px-2.5 py-0.5 rounded-lg text-xs font-bold transition <?= $mode === 'preset' ? 'bg-amber-600 text-white' : 'bg-white text-slate-700' ?>">
                    Persis Foto
                </a>
            </div>
        </div>
    </div>

    <!-- KERTAS STRUK THERMAL (DILENGKAPI METODE BAYAR, JUMLAH BAYAR & KEMBALIAN) -->
    <div class="paper-receipt w-full <?= $width_class ?> p-5 md:p-6 font-receipt leading-snug select-none">
        
        <?php if ($mode === 'preset'): ?>
            <!-- ========================================== -->
            <!-- MODE CONTOH PERSIS FOTO BERGHOTEL SCHEIDEGG -->
            <!-- ========================================== -->

            <!-- Header Toko -->
            <div class="text-center font-bold space-y-0.5 mb-4">
                <p class="text-base tracking-wide">Berghotel</p>
                <p class="text-base tracking-wide font-extrabold">Grosse Scheidegg</p>
                <p class="text-xs font-normal">3818 Grindelwald</p>
                <p class="text-xs font-normal">Familie R. Müller</p>
            </div>

            <!-- Meta Transaksi 2 Kolom -->
            <div class="flex justify-between items-start text-[11px] mb-4 border-b border-dashed border-slate-300 pb-2">
                <div class="space-y-0.5">
                    <p>Rech. Nr. 4572</p>
                    <p id="strukMetaMetodePreset">Bar (<?= htmlspecialchars($metode_bayar) ?>)</p>
                </div>
                <div class="space-y-0.5 text-right">
                    <p>30.07.2007/13:29:17</p>
                    <p>Tisch &nbsp; 7/01</p>
                </div>
            </div>

            <!-- Daftar Item Belanja -->
            <div class="space-y-1.5 text-[11px] mb-2">
                <div class="flex justify-between items-baseline">
                    <span class="flex-1 truncate pr-1">2xLatte Macchiato</span>
                    <span class="px-1 text-center">à</span>
                    <span class="w-12 text-right">4.50</span>
                    <span class="w-8 text-center text-[10px]">CHF</span>
                    <span class="w-14 text-right font-bold">9.00</span>
                </div>
                <div class="flex justify-between items-baseline">
                    <span class="flex-1 truncate pr-1">1xGloki</span>
                    <span class="px-1 text-center">à</span>
                    <span class="w-12 text-right">5.00</span>
                    <span class="w-8 text-center text-[10px]">CHF</span>
                    <span class="w-14 text-right font-bold">5.00</span>
                </div>
                <div class="flex justify-between items-baseline">
                    <span class="flex-1 truncate pr-1">1xSchweinschnitzel</span>
                    <span class="px-1 text-center">à</span>
                    <span class="w-12 text-right">22.00</span>
                    <span class="w-8 text-center text-[10px]">CHF</span>
                    <span class="w-14 text-right font-bold">22.00</span>
                </div>
                <div class="flex justify-between items-baseline">
                    <span class="flex-1 truncate pr-1">1xChässpätzli</span>
                    <span class="px-1 text-center">à</span>
                    <span class="w-12 text-right">18.50</span>
                    <span class="w-8 text-center text-[10px]">CHF</span>
                    <span class="w-14 text-right font-bold">18.50</span>
                </div>
            </div>

            <!-- Garis Putus-putus Sebelum Total -->
            <div class="flex justify-end my-1">
                <span class="font-bold tracking-tight text-xs">----------------</span>
            </div>

            <!-- Baris Total Besar -->
            <div class="flex justify-between items-baseline pt-1 mb-3">
                <div class="flex items-baseline gap-2 pl-4">
                    <span class="text-xs font-normal">Total :</span>
                    <span class="text-xs font-normal">CHF</span>
                </div>
                <div class="text-xl md:text-2xl font-black tracking-tight text-right pr-0.5">
                    54.50
                </div>
            </div>

            <!-- BAGIAN RINCIAN PEMBAYARAN, METODE, DAN KEMBALIAN -->
            <div class="border-t border-b border-dashed border-slate-300 py-2 my-2 space-y-1 text-[11px]">
                <div class="flex justify-between items-baseline">
                    <span class="text-slate-600">Zahlart / Metode :</span>
                    <span id="textStrukMetodePreset" class="font-bold text-slate-900"><?= htmlspecialchars($metode_bayar) ?></span>
                </div>
                <div class="flex justify-between items-baseline">
                    <span class="text-slate-600">Gegeben / Bayar  :</span>
                    <span class="font-bold text-slate-900">CHF <span id="textStrukBayarPreset"><?= number_format($uang_bayar, 2, '.', '') ?></span></span>
                </div>
                <div class="flex justify-between items-baseline font-bold text-slate-900">
                    <span>Zurück / Kembali :</span>
                    <span>CHF <span id="textStrukKembaliPreset"><?= number_format($uang_kembali, 2, '.', '') ?></span></span>
                </div>
            </div>

            <!-- Rincian Pajak / MwSt -->
            <div class="flex justify-between items-baseline text-[10px] mb-3 pt-1">
                <span>Incl. 7.6% MwSt 54.50 CHF:</span>
                <span class="text-right font-bold">3.85</span>
            </div>

            <!-- Informasi Tambahan & Kasir -->
            <div class="space-y-0.5 text-[10px] mb-2">
                <p>Entspricht in Euro &nbsp; 36.33 EUR</p>
                <p>Es bediente Sie: Ursula</p>
            </div>

            <!-- Bagian Terima Kasih -->
            <div class="text-center text-[10px] border-t border-dashed border-slate-300 pt-2.5 mt-2 space-y-0.5">
                <p class="font-bold tracking-wider uppercase">*** TERIMA KASIH ***</p>
                <p class="text-[9px] text-slate-600">Vielen Dank für Ihren Besuch</p>
            </div>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- MODE DATA TOKOKITA (DATABASE ASLI SISTEM) -->
            <!-- ========================================== -->

            <!-- Header Toko -->
            <div class="text-center font-bold space-y-0.5 mb-4">
                <p class="text-base tracking-wide uppercase">TOKOKITA</p>
                <p class="text-base tracking-wide font-extrabold uppercase">Enterprise Mart</p>
                <p class="text-xs font-normal">3818 Ponorogo - Jawa Timur</p>
                <p class="text-xs font-normal">Cabang Pusat Indonesia</p>
            </div>

            <!-- Meta Transaksi 2 Kolom -->
            <div class="flex justify-between items-start text-[11px] mb-4 border-b border-dashed border-slate-300 pb-2">
                <div class="space-y-0.5">
                    <p>Rech. Nr. <?= str_pad($data_trx['id_transaksi'] ?? 1, 4, '0', STR_PAD_LEFT) ?></p>
                    <p id="strukMetaMetode">Metode: <span class="font-bold"><?= htmlspecialchars($metode_bayar) ?></span></p>
                </div>
                <div class="space-y-0.5 text-right">
                    <p><?= $waktu_struk ?></p>
                    <p>Kasir: <?= htmlspecialchars($nama_kasir) ?></p>
                </div>
            </div>

            <!-- Daftar Item Belanja -->
            <div class="space-y-1.5 text-[11px] mb-2">
                <?php if (empty($item_belanja)): ?>
                    <div class="flex justify-between items-baseline">
                        <span class="flex-1 truncate pr-1">1xPaket Belanja Toko</span>
                        <span class="px-1 text-center">à</span>
                        <span class="text-right"><?= number_format($total_belanja, 0, ',', '.') ?></span>
                        <span class="w-8 text-center text-[10px]">Rp</span>
                        <span class="text-right font-bold"><?= number_format($total_belanja, 0, ',', '.') ?></span>
                    </div>
                <?php else: ?>
                    <?php foreach ($item_belanja as $it): ?>
                    <div class="flex justify-between items-baseline">
                        <span class="flex-1 truncate pr-1" title="<?= htmlspecialchars($it['nama_produk']) ?>">
                            <?= $it['jumlah'] ?>x<?= htmlspecialchars($it['nama_produk']) ?>
                        </span>
                        <span class="px-1 text-center">à</span>
                        <span class="text-right"><?= number_format($it['harga'], 0, ',', '.') ?></span>
                        <span class="w-8 text-center text-[10px]">Rp</span>
                        <span class="text-right font-bold"><?= number_format($it['subtotal_item'], 0, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Garis Putus-putus Sebelum Total -->
            <div class="flex justify-end my-1">
                <span class="font-bold tracking-tight text-xs">----------------</span>
            </div>

            <!-- Baris Total Besar -->
            <div class="flex justify-between items-baseline pt-1 mb-3">
                <div class="flex items-baseline gap-2 pl-4">
                    <span class="text-xs font-normal">Total :</span>
                    <span class="text-xs font-normal">Rp</span>
                </div>
                <div class="text-xl md:text-2xl font-black tracking-tight text-right pr-0.5">
                    <?= number_format($total_belanja, 0, ',', '.') ?>
                </div>
            </div>

            <!-- BAGIAN RINCIAN PEMBAYARAN, METODE, DAN KEMBALIAN DI STRUK -->
            <div class="border-t border-b border-dashed border-slate-300 py-2 my-2 space-y-1 text-[11px]">
                <div class="flex justify-between items-baseline">
                    <span class="text-slate-600">Metode Bayar :</span>
                    <span id="textStrukMetode" class="font-bold text-slate-900"><?= htmlspecialchars($metode_bayar) ?></span>
                </div>
                <?php if (!empty($data_trx['ref_pembayaran'])): ?>
                <div class="flex justify-between items-baseline text-[10px]">
                    <span class="text-slate-500">Ref / No. Trx :</span>
                    <span class="font-mono text-slate-700"><?= htmlspecialchars($data_trx['ref_pembayaran']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($data_trx['status_pembayaran'])): ?>
                <div class="flex justify-between items-baseline text-[10px]">
                    <span class="text-slate-500">Status Bayar :</span>
                    <span class="font-bold <?= strpos($data_trx['status_pembayaran'], 'Lunas') !== false ? 'text-emerald-700' : 'text-amber-700' ?>"><?= htmlspecialchars($data_trx['status_pembayaran']) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between items-baseline">
                    <span class="text-slate-600">Bayar        :</span>
                    <span class="font-bold text-slate-900">Rp <span id="textStrukBayar"><?= number_format($uang_bayar, 0, ',', '.') ?></span></span>
                </div>
                <div class="flex justify-between items-baseline font-bold text-slate-900">
                    <span>Kembalian    :</span>
                    <span>Rp <span id="textStrukKembali"><?= number_format($uang_kembali, 0, ',', '.') ?></span></span>
                </div>
            </div>

            <!-- Rincian Pajak / PPN 11% -->
            <div class="flex justify-between items-baseline text-[10px] mb-3 pt-1">
                <span>Incl. 11% PPN:</span>
                <span class="text-right font-bold">Rp <?= number_format($ppn_nominal, 0, ',', '.') ?></span>
            </div>

            <!-- Informasi Tambahan, Pengiriman & Kasir -->
            <div class="space-y-0.5 text-[10px] mb-2">
                <p class="truncate">Pelanggan: <?= htmlspecialchars($data_trx['nama_pelanggan'] ?? 'Pelanggan Umum') ?> (<?= htmlspecialchars($data_trx['kota'] ?? 'Ponorogo') ?>)</p>
                <?php if (!empty($data_trx['no_telepon'])): ?>
                    <p class="text-slate-700">Telp/WA: <?= htmlspecialchars($data_trx['no_telepon']) ?></p>
                <?php endif; ?>
                <?php if (!empty($data_trx['alamat_pengiriman'])): ?>
                    <p class="text-slate-700 leading-tight">Kirim ke: <?= htmlspecialchars($data_trx['alamat_pengiriman']) ?></p>
                <?php endif; ?>
                <?php if (!empty($data_trx['catatan_pengiriman'])): ?>
                    <p class="text-slate-500 italic">Catatan: "<?= htmlspecialchars($data_trx['catatan_pengiriman']) ?>"</p>
                <?php endif; ?>
                <p>Kasir: <?= htmlspecialchars($nama_kasir) ?></p>
            </div>

            <!-- Bagian Terima Kasih -->
            <div class="text-center text-[10px] border-t border-dashed border-slate-300 pt-2.5 mt-2 space-y-0.5">
                <p class="font-bold tracking-wider uppercase">*** TERIMA KASIH ***</p>
                <p class="text-[9.5px] text-slate-700">Atas Kunjungan & Belanja Anda</p>
                <p class="text-[8.5px] text-slate-500">Barang yang dibeli tidak dapat ditukar/dikembalikan</p>
            </div>

        <?php endif; ?>

    </div>

    <!-- Tombol Ekstra Cetak Bawah (Tidak Tercetak) -->
    <div class="no-print mt-5 mb-8 text-center space-x-2">
        <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-lg inline-flex items-center gap-1.5 transition">
            <i data-lucide="printer" class="w-4 h-4"></i> Cetak Struk Sekarang
        </button>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL SCANNER BARCODE & QR PEMBAYARAN                    -->
    <!-- Mendukung: Kamera Webcam/HP, USB Barcode Gun, Simulasi   -->
    <!-- ======================================================== -->
    <div id="modalScannerPembayaran" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto no-print">
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
                <button type="button" onclick="tutupModalScanner()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Body Modal -->
            <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto">
                
                <!-- Tab Switcher -->
                <div class="flex items-center bg-slate-100 p-1 rounded-2xl text-xs font-bold">
                    <button type="button" id="tabKameraBtn" onclick="pilihTabScanner('kamera')" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm">
                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Kamera Live
                    </button>
                    <button type="button" id="tabGunBtn" onclick="pilihTabScanner('gun')" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                        <i data-lucide="barcode" class="w-3.5 h-3.5"></i> Barcode Gun USB
                    </button>
                    <button type="button" id="tabSimBtn" onclick="pilihTabScanner('sim')" class="flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i> Uji Demo
                    </button>
                </div>

                <!-- KONTEN 1: KAMERA SCANNER LIVE -->
                <div id="scannerContentKamera" class="space-y-3">
                    <div class="relative bg-slate-900 rounded-2xl overflow-hidden aspect-video max-h-[250px] flex items-center justify-center border-2 border-slate-700 shadow-inner">
                        <div id="readerScanner" class="w-full h-full flex items-center justify-center"></div>
                        <div id="scanLaserLine" class="scanning-laser"></div>

                        <div id="cameraPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 bg-slate-900/90 z-10">
                            <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                                <i data-lucide="camera" class="w-6 h-6"></i>
                            </div>
                            <p class="text-xs font-bold text-white mb-1">Kamera Scanner Siap Digunakan</p>
                            <p class="text-[11px] text-slate-400 mb-3 max-w-xs">Gunakan webcam atau kamera HP untuk memindai barcode/QRIS pembayaran pelanggan</p>
                            <button type="button" onclick="startCameraScanner()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-1.5 transition">
                                <i data-lucide="video" class="w-3.5 h-3.5"></i> Nyalakan Kamera
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            <span>Arahkan kode QR / Barcode pembayaran ke bingkai kamera</span>
                        </div>
                        <button type="button" id="btnToggleCam" onclick="stopCameraScanner()" class="text-xs font-bold text-rose-600 hover:text-rose-700 px-2 py-1 rounded-lg hover:bg-rose-50 transition hidden">
                            Matikan Kamera
                        </button>
                    </div>
                </div>

                <!-- KONTEN 2: BARCODE GUN USB INPUT -->
                <div id="scannerContentGun" class="hidden space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center">
                            <i data-lucide="scan-barcode" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-slate-800">Scanner Fisik Barcode Gun USB</h4>
                            <p class="text-[11px] text-slate-500">Tembakkan barcode gun ke kode QR atau barcode nota</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Input Barcode Gun (Auto-Enter):</label>
                        <div class="relative">
                            <i data-lucide="barcode" class="w-4 h-4 text-slate-400 absolute left-3 top-3"></i>
                            <input type="text" id="inputScannerGun" placeholder="Arahkan barcode gun lalu tembak di sini..."
                                class="w-full pl-10 pr-20 py-2.5 bg-white border-2 border-indigo-300 rounded-xl text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm"
                                autocomplete="off">
                            <button type="button" onclick="submitGunScan()" class="absolute right-2 top-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold px-3 py-1 rounded-lg transition">
                                Proses
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 italic">*Alat Barcode Gun USB otomatis mengirim tombol Enter setelah membaca kode.</p>
                    </div>
                </div>

                <!-- KONTEN 3: PENGUJIAN CEPAT / DEMO SIMULASI -->
                <div id="scannerContentSim" class="hidden space-y-3">
                    <div class="bg-indigo-50/70 border border-indigo-200 p-3 rounded-2xl">
                        <p class="text-xs font-bold text-indigo-900 mb-1 flex items-center gap-1.5">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i> Pengujian 1-Klik Simulasi:
                        </p>
                        <p class="text-[11px] text-indigo-700">Klik salah satu metode pembayaran di bawah untuk mensimulasikan hasil scan:</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <button type="button" onclick="simulasiScan('QRIS-NMID-00020101021226590014ID-BCA-88902')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                QR
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-indigo-600">QRIS BCA / Nasional</p>
                                <p class="text-[10px] font-mono text-slate-400">NMID: ID-BCA-88902</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScan('DANA-PAY-081234567890-REF-992384')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                DN
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-blue-600">DANA Dompet Digital</p>
                                <p class="text-[10px] font-mono text-slate-400">DANA-081234567890</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScan('SHOPEEPAY-SPAY-TRX-7749201948')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-amber-500 hover:bg-amber-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                SP
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-amber-600">ShopeePay QR</p>
                                <p class="text-[10px] font-mono text-slate-400">SPAY-TRX-774920</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScan('OVO-CASH-PAY-8839102934')"
                            class="p-2.5 rounded-xl border border-slate-200 hover:border-purple-500 hover:bg-purple-50/50 text-left transition flex items-center gap-2 group bg-white shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center font-black text-[10px] group-hover:scale-105 transition">
                                OV
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 group-hover:text-purple-600">OVO Payment</p>
                                <p class="text-[10px] font-mono text-slate-400">OVO-8839102934</p>
                            </div>
                        </button>

                        <button type="button" onclick="simulasiScan('MBR-TK-009482-SALDO-TOKOKITA')"
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
                    <span class="font-semibold text-slate-700">Total: <?= $mode === 'preset' ? 'CHF 54.50' : rupiah($total_belanja) ?></span>
                </div>

            </div>

        </div>
    </div>

    <!-- Script Library JS TokoKita -->
    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        const isPreset = <?= $mode === 'preset' ? 'true' : 'false' ?>;
        const totalHarga = <?= $mode === 'preset' ? 54.50 : $total_belanja ?>;

        function updateBayarKembalian() {
            const inputBayar = document.getElementById('inputBayar');
            const selectMetode = document.getElementById('selectMetode');
            const metode = selectMetode.value;

            let bayar = parseFloat(inputBayar.value) || 0;
            let kembali = bayar - totalHarga;
            if (kembali < 0) kembali = 0;

            if (isPreset) {
                const elMetode = document.getElementById('textStrukMetodePreset');
                const elBayar = document.getElementById('textStrukBayarPreset');
                const elKembali = document.getElementById('textStrukKembaliPreset');
                const elMeta = document.getElementById('strukMetaMetodePreset');

                if (elMetode) elMetode.innerText = metode;
                if (elBayar) elBayar.innerText = bayar.toFixed(2);
                if (elKembali) elKembali.innerText = kembali.toFixed(2);
                if (elMeta) elMeta.innerText = 'Bar (' + metode + ')';
            } else {
                const elMetode = document.getElementById('textStrukMetode');
                const elBayar = document.getElementById('textStrukBayar');
                const elKembali = document.getElementById('textStrukKembali');
                const elMeta = document.getElementById('strukMetaMetode');

                const formatNum = (num) => new Intl.NumberFormat('id-ID').format(num);

                if (elMetode) elMetode.innerText = metode;
                if (elBayar) elBayar.innerText = formatNum(bayar);
                if (elKembali) elKembali.innerText = formatNum(kembali);
                if (elMeta) elMeta.innerHTML = 'Metode: <span class="font-bold">' + metode + '</span>';
            }
        }

        function onMetodeChange() {
            const selectMetode = document.getElementById('selectMetode');
            // Jika memilih pembayaran non-tunai (DANA, QRIS, ShopeePay, dll.), default ke uang pas
            if (selectMetode.value !== 'Tunai') {
                setUangPas();
            } else {
                updateBayarKembalian();
            }
        }

        function setUangPas() {
            document.getElementById('inputBayar').value = totalHarga;
            updateBayarKembalian();
        }

        function setNominalBayar(nominal) {
            document.getElementById('inputBayar').value = nominal;
            updateBayarKembalian();
        }

        // ============================================
        // LOGIKA SCANNER BARCODE & QR PEMBAYARAN KASIR
        // ============================================
        let html5QrScanner = null;
        let isCamRunning = false;

        function bukaModalScanner() {
            const modal = document.getElementById('modalScannerPembayaran');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                pilihTabScanner('kamera');
                refreshIcons();
            }
        }

        function tutupModalScanner() {
            stopCameraScanner();
            const modal = document.getElementById('modalScannerPembayaran');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function pilihTabScanner(tab) {
            const btnKam = document.getElementById('tabKameraBtn');
            const btnGun = document.getElementById('tabGunBtn');
            const btnSim = document.getElementById('tabSimBtn');

            const conKam = document.getElementById('scannerContentKamera');
            const conGun = document.getElementById('scannerContentGun');
            const conSim = document.getElementById('scannerContentSim');

            [btnKam, btnGun, btnSim].forEach(b => {
                b.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900';
            });
            [conKam, conGun, conSim].forEach(c => c.classList.add('hidden'));

            if (tab === 'kamera') {
                btnKam.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm';
                conKam.classList.remove('hidden');
            } else if (tab === 'gun') {
                stopCameraScanner();
                btnGun.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm';
                conGun.classList.remove('hidden');
                setTimeout(() => {
                    const gunInp = document.getElementById('inputScannerGun');
                    if (gunInp) gunInp.focus();
                }, 100);
            } else if (tab === 'sim') {
                stopCameraScanner();
                btnSim.className = 'flex-1 py-2 rounded-xl transition text-center flex items-center justify-center gap-1.5 bg-white text-indigo-700 shadow-sm';
                conSim.classList.remove('hidden');
            }
            refreshIcons();
        }

        function startCameraScanner() {
            if (typeof Html5Qrcode === 'undefined') {
                alert('Library Barcode Scanner sedang dimuat, mohon tunggu sebentar...');
                return;
            }

            document.getElementById('cameraPlaceholder').classList.add('hidden');
            document.getElementById('btnToggleCam').classList.remove('hidden');

            if (!html5QrScanner) {
                html5QrScanner = new Html5Qrcode("readerScanner");
            }

            const config = { fps: 15, qrbox: { width: 220, height: 220 } };

            html5QrScanner.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    prosesHasilScan(decodedText);
                },
                (errorMessage) => {
                    // Scanning frame callback
                }
            ).then(() => {
                isCamRunning = true;
            }).catch(err => {
                console.warn("Gagal memulai kamera scanner:", err);
                document.getElementById('cameraPlaceholder').classList.remove('hidden');
                document.getElementById('btnToggleCam').classList.add('hidden');
                alert("Tidak dapat mengakses kamera. Pastikan Anda memberikan izin akses kamera atau gunakan Tab Barcode Gun / Uji Demo.");
            });
        }

        function stopCameraScanner() {
            if (html5QrScanner && isCamRunning) {
                html5QrScanner.stop().then(() => {
                    isCamRunning = false;
                    document.getElementById('cameraPlaceholder').classList.remove('hidden');
                    document.getElementById('btnToggleCam').classList.add('hidden');
                }).catch(err => console.error("Error stop camera:", err));
            }
        }

        function prosesHasilScan(rawCode) {
            if (!rawCode) return;

            // Bunyikan Audio Beep Scanner Kasir
            playScannerBeep();

            // Analisis Payload Barcode/QR
            const parsed = parsePaymentBarcode(rawCode);

            // Update Form Pembayaran di Layar
            const selectMetode = document.getElementById('selectMetode');
            if (selectMetode) {
                selectMetode.value = parsed.metode;
            }
            setUangPas();

            // Tampilkan Status Barcode di Panel Layar
            const boxStatus = document.getElementById('boxStatusScan');
            const lblMetode = document.getElementById('labelMetodeScan');
            const lblRef    = document.getElementById('labelRefScan');
            if (boxStatus && lblMetode && lblRef) {
                lblMetode.innerText = parsed.metode;
                lblRef.innerText = parsed.refCode;
                boxStatus.classList.remove('hidden');
            }

            // Tampilkan Referensi Barcode di Kertas Struk Fisik Thermal
            const rowRef = document.getElementById('rowStrukRef');
            const textRef = document.getElementById('textStrukRef');
            if (rowRef && textRef) {
                rowRef.classList.remove('hidden');
                textRef.innerText = parsed.refCode;
            }
            const rowRefPreset = document.getElementById('rowStrukRefPreset');
            const textRefPreset = document.getElementById('textStrukRefPreset');
            if (rowRefPreset && textRefPreset) {
                rowRefPreset.classList.remove('hidden');
                textRefPreset.innerText = parsed.refCode;
            }

            tutupModalScanner();
            tampilkanToast("✅ Barcode Sukses Di-scan: " + parsed.label, "success");
            refreshIcons();
        }

        function resetBarcodeRef() {
            const boxStatus = document.getElementById('boxStatusScan');
            if (boxStatus) boxStatus.classList.add('hidden');

            const rowRef = document.getElementById('rowStrukRef');
            if (rowRef) rowRef.classList.add('hidden');

            const rowRefPreset = document.getElementById('rowStrukRefPreset');
            if (rowRefPreset) rowRefPreset.classList.add('hidden');

            const selectMetode = document.getElementById('selectMetode');
            if (selectMetode) selectMetode.value = 'Tunai';
            updateBayarKembalian();
            tampilkanToast("Referensi barcode di-reset ke Tunai.", "info");
        }

        function simulasiScan(sampleCode) {
            prosesHasilScan(sampleCode);
        }

        function submitGunScan() {
            const inputGun = document.getElementById('inputScannerGun');
            if (inputGun && inputGun.value.trim() !== '') {
                prosesHasilScan(inputGun.value.trim());
                inputGun.value = '';
            }
        }

        // Listener Enter untuk Barcode Gun USB
        document.addEventListener('DOMContentLoaded', () => {
            const inputGun = document.getElementById('inputScannerGun');
            if (inputGun) {
                inputGun.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitGunScan();
                    }
                });
            }
        });
    </script>
</body>
</html>
