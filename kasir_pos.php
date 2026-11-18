<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif = "";
$error = "";
$last_trx_id = 0;

// Transaksi Instan 1 Klik via Stored Procedure sp_TambahTransaksiBaru dengan Pengecekan Stok Ketat
if (isset($_POST['bayar_instan'])) {
    $id_pel = (int)$_POST['id_pelanggan'];
    $id_prd = (int)$_POST['id_produk'];
    $qty    = max(1, (int)$_POST['jumlah']);
    $disc   = (float)$_POST['diskon'];

    // Cek Stok Produk Terkini (Mencegah Checkout Melebihi Stok)
    $q_stk_pos = mysqli_query($conn, "SELECT nama_produk, stok FROM tbl_produk WHERE id_produk = $id_prd");
    $r_stk_pos = mysqli_fetch_assoc($q_stk_pos);

    if (!$r_stk_pos) {
        $error = "Produk tidak ditemukan dalam sistem!";
    } elseif ($r_stk_pos['stok'] <= 0) {
        $error = "Transaksi gagal: Stok '{$r_stk_pos['nama_produk']}' saat ini sudah habis (0 unit)!";
    } elseif ($qty > $r_stk_pos['stok']) {
        $error = "Transaksi ditolak! Jumlah yang diminta ($qty unit) melebihi stok yang tersedia ({$r_stk_pos['stok']} unit) untuk '{$r_stk_pos['nama_produk']}'!";
    } else {
        if (mysqli_query($conn, "CALL sp_TambahTransaksiBaru($id_pel, $id_prd, $qty, $disc)")) {
            while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
            
            $q_last = mysqli_query($conn, "SELECT id_transaksi FROM tbl_transaksi ORDER BY id_transaksi DESC LIMIT 1");
            if ($r_last = mysqli_fetch_assoc($q_last)) {
                $last_trx_id = (int)$r_last['id_transaksi'];
            }

            // Perbarui metode bayar transaksi sesuai pilihan kasir
            if (isset($_POST['metode_pos'])) {
                $metode_pos = mysqli_real_escape_string($conn, $_POST['metode_pos']);
                mysqli_query($conn, "UPDATE tbl_transaksi SET metode_pembayaran = '$metode_pos' WHERE id_transaksi = $last_trx_id");
            }

            $notif = "Transaksi Kasir POS Berhasil! Stok produk '{$r_stk_pos['nama_produk']}' otomatis terpotong {$qty} unit oleh Trigger Database.";
        } else {
            $error = "Gagal memproses transaksi: " . mysqli_error($conn);
        }
    }
}

$q_produk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM tbl_produk p 
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
    WHERE p.stok > 0
    ORDER BY p.id_produk DESC
");

$q_pel = mysqli_query($conn, "SELECT * FROM view_pelanggan ORDER BY nama_pelanggan ASC");
$q_kat = mysqli_query($conn, "SELECT * FROM tbl_kategori ORDER BY nama_kategori ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Point of Sale (POS) - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Library Barcode & QR Code Scanner HTML5 (Lokal & CDN Fallback) -->
    <script src="js/html5-qrcode.min.js"></script>
    <script>
        if (typeof Html5Qrcode === 'undefined') {
            document.write('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"><\/script>');
        }
    </script>
    <style>
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
        #readerPOS video {
            border-radius: 1rem;
            object-fit: cover;
            width: 100% !important;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-5">
        
        <!-- Notifikasi -->
        <?php if ($notif): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 p-4 rounded-2xl text-xs font-semibold flex flex-wrap items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <p class="font-bold text-sm text-emerald-800"><?= $notif ?></p>
                        <p class="text-[11px] text-emerald-600">ID Transaksi: #TRX-<?= $last_trx_id ?></p>
                    </div>
                </div>
                <?php if ($last_trx_id > 0): ?>
                <div class="flex items-center gap-2">
                    <a href="struk.php?id=<?= $last_trx_id ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl font-bold shadow flex items-center gap-1.5 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Langsung Cetak Struk 58mm →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <!-- ==================================================== -->
        <!-- HERO PANEL: SCANNER BARCODE PRODUK UNTUK KASIR POS   -->
        <!-- ==================================================== -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-5 md:p-6 rounded-3xl shadow-xl border border-indigo-900/60 relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wide">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <i data-lucide="scan-barcode" class="w-3.5 h-3.5"></i> Scanner Barcode Produk Siap
                    </div>
                    <h2 class="text-lg md:text-xl font-black text-white flex items-center gap-2">
                        Scan Barcode untuk Memasukkan / Menambahkan Produk
                    </h2>
                    <p class="text-xs text-slate-300 max-w-xl">
                        Tembak barcode produk dengan Barcode Scanner Gun USB atau Kamera HP/Webcam. Produk otomatis dicari dan ditambahkan ke form kasir dengan bunyi beep!
                    </p>
                </div>

                <!-- Tombol Kamera Scanner -->
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="bukaModalKameraProduk()" 
                        class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-extrabold px-4 py-2.5 rounded-2xl text-xs shadow-lg shadow-emerald-500/30 flex items-center gap-2 transition active:scale-95">
                        <i data-lucide="camera" class="w-4 h-4"></i> Buka Kamera Scanner
                    </button>
                </div>
            </div>

            <!-- Input Barcode Gun & Quick Demo Chips -->
            <div class="mt-4 pt-4 border-t border-slate-800/80 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                
                <!-- Input Tembak Barcode Gun USB -->
                <div class="md:col-span-5 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-indigo-400">
                        <i data-lucide="barcode" class="w-5 h-5"></i>
                    </div>
                    <input type="text" id="inputBarcodeProduk" autofocus autocomplete="off"
                        placeholder="Scan / Ketik Barcode di sini (tekan Enter)..."
                        class="w-full bg-slate-800/90 border-2 border-indigo-500/60 focus:border-indigo-400 pl-11 pr-24 py-2.5 rounded-2xl text-xs font-mono font-bold text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 shadow-inner transition">
                    <button type="button" onclick="submitManualBarcodeProduk()"
                        class="absolute right-1.5 top-1.5 bottom-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold px-3 rounded-xl transition flex items-center gap-1">
                        <span>+ Masuk</span>
                    </button>
                </div>

                <!-- Quick Test Barcode Demo Chips -->
                <div class="md:col-span-7 flex flex-wrap items-center gap-1.5 text-[11px]">
                    <span class="text-slate-400 text-[10px] font-bold mr-1">⚡ Demo Barcode:</span>
                    <button type="button" onclick="scanBarcodeDanTambah('89900000001')" class="bg-slate-800/80 hover:bg-indigo-600 hover:text-white text-slate-200 border border-slate-700 px-2.5 py-1 rounded-xl font-bold transition flex items-center gap-1">
                        🌾 Beras (89900000001)
                    </button>
                    <button type="button" onclick="scanBarcodeDanTambah('89900000002')" class="bg-slate-800/80 hover:bg-indigo-600 hover:text-white text-slate-200 border border-slate-700 px-2.5 py-1 rounded-xl font-bold transition flex items-center gap-1">
                        🌻 Minyak (89900000002)
                    </button>
                    <button type="button" onclick="scanBarcodeDanTambah('89900000003')" class="bg-slate-800/80 hover:bg-indigo-600 hover:text-white text-slate-200 border border-slate-700 px-2.5 py-1 rounded-xl font-bold transition flex items-center gap-1">
                        ☕ Kopi (89900000003)
                    </button>
                    <button type="button" onclick="scanBarcodeDanTambah('89900000004')" class="bg-slate-800/80 hover:bg-indigo-600 hover:text-white text-slate-200 border border-slate-700 px-2.5 py-1 rounded-xl font-bold transition flex items-center gap-1">
                        📓 Buku (89900000004)
                    </button>
                    <button type="button" onclick="scanBarcodeDanTambah('89900000005')" class="bg-slate-800/80 hover:bg-indigo-600 hover:text-white text-slate-200 border border-slate-700 px-2.5 py-1 rounded-xl font-bold transition flex items-center gap-1">
                        💧 AQUAVIVA (89900000005)
                    </button>
                </div>
            </div>

            <!-- Notifikasi Status Hasil Scan Realtime -->
            <div id="statusScanProduk" class="hidden mt-3 p-2.5 rounded-xl text-xs font-bold flex items-center justify-between border transition">
                <div class="flex items-center gap-2">
                    <span id="iconStatusScan"></span>
                    <span id="pesanStatusScan"></span>
                </div>
                <button type="button" onclick="document.getElementById('statusScanProduk').classList.add('hidden')" class="hover:opacity-75">✕</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Katalog Produk POS (8 Kolom) -->
            <div class="lg:col-span-8 bg-white p-5 md:p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h1 class="font-black text-base text-slate-800 flex items-center gap-2">
                            <i data-lucide="calculator" class="w-5 h-5 text-indigo-600"></i> Terminal Kasir POS Modern
                        </h1>
                        <p class="text-xs text-slate-400">Pilih produk katalog untuk transaksi instan atau multi-keranjang</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="keranjang.php" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-indigo-200">
                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Mode Multi-Keranjang →
                        </a>
                    </div>
                </div>

                <!-- Live Search & Filter Produk -->
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariProdukPOS" onkeyup="filterProdukPOS()" placeholder="Cari nama barang atau kategori SKU..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                </div>

                <!-- Grid Item Produk -->
                <div id="gridProdukPOS" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 max-h-[580px] overflow-y-auto pr-1">
                    <?php 
                    mysqli_data_seek($q_produk, 0); 
                    while ($p = mysqli_fetch_assoc($q_produk)): 
                        $stok_warna = $p['stok'] > 10 ? 'text-slate-400' : 'text-amber-600 font-bold';
                    ?>
                    <div class="pos-card border border-slate-200/70 rounded-2xl p-3.5 bg-white hover:border-indigo-500 hover:shadow-md transition flex flex-col justify-between group cursor-pointer"
                         onclick="pilihProduk(<?= $p['id_produk'] ?>, '<?= addslashes($p['nama_produk']) ?>', <?= $p['harga'] ?>)">
                        
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[9px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-md">
                                    <?= htmlspecialchars($p['nama_kategori'] ?? 'Umum') ?>
                                </span>
                                <span class="text-[10px] <?= $stok_warna ?>">
                                    Stok: <?= $p['stok'] ?>
                                </span>
                            </div>
                            <h3 class="font-bold text-xs text-slate-800 group-hover:text-indigo-600 transition line-clamp-2">
                                <?= htmlspecialchars($p['nama_produk']) ?>
                            </h3>
                            <div class="mt-1 flex items-center gap-1 font-mono text-[10px] text-slate-500">
                                <i data-lucide="barcode" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span><?= htmlspecialchars($p['barcode'] ?? ('899' . str_pad($p['id_produk'], 8, '0', STR_PAD_LEFT))) ?></span>
                            </div>
                        </div>

                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-sm font-black text-emerald-600"><?= rupiah($p['harga']) ?></span>
                            <button type="button" class="bg-indigo-50 group-hover:bg-indigo-600 group-hover:text-white text-indigo-600 px-2.5 py-1 rounded-xl text-[11px] font-bold transition flex items-center gap-1">
                                <i data-lucide="plus" class="w-3 h-3"></i> Pilih
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

            </div>

            <!-- Form Kasir Express 1-Klik (4 Kolom) -->
            <form method="POST" class="lg:col-span-4 bg-white p-5 md:p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4 sticky top-24">
                
                <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-extrabold text-sm text-slate-800 flex items-center gap-1.5">
                            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i> Checkout Kasir Express
                        </h2>
                        <p class="text-[11px] text-slate-400">Stored Procedure `sp_TambahTransaksiBaru`</p>
                    </div>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                </div>

                <!-- Input Pelanggan -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Pelanggan</label>
                    <select name="id_pelanggan" required class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php while ($pel = mysqli_fetch_assoc($q_pel)): ?>
                            <option value="<?= $pel['id_pelanggan'] ?>">
                                <?= htmlspecialchars($pel['nama_pelanggan']) ?> (<?= htmlspecialchars($pel['kota']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Produk Terpilih -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Pilih Produk</label>
                    <select id="selectProduk" name="id_produk" required onchange="onProdukChange()" class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php mysqli_data_seek($q_produk, 0); while ($pr = mysqli_fetch_assoc($q_produk)): ?>
                            <option value="<?= $pr['id_produk'] ?>" data-harga="<?= $pr['harga'] ?>">
                                <?= htmlspecialchars($pr['nama_produk']) ?> — <?= rupiah($pr['harga']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Kuantitas & Diskon -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Kuantitas (Qty)</label>
                        <input type="number" id="inputQty" name="jumlah" value="1" min="1" required oninput="hitungTotalExpress()"
                            class="w-full border border-slate-200 p-2.5 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Diskon (%)</label>
                        <input type="number" id="inputDiskon" name="diskon" value="0" min="0" max="100" oninput="hitungTotalExpress()"
                            class="w-full border border-slate-200 p-2.5 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Panel Scanner Barcode / QRIS Pembayaran POS -->
                <div class="bg-indigo-50/70 p-3 rounded-2xl border border-indigo-200/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-700 flex items-center gap-1">
                            <i data-lucide="wallet" class="w-3.5 h-3.5 text-indigo-600"></i> Cara Bayar:
                        </span>
                        <button type="button" onclick="bukaModalScannerPOS()"
                            class="bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white px-2.5 py-1 rounded-xl text-[10.5px] font-bold shadow flex items-center gap-1 transition active:scale-95">
                            <i data-lucide="scan" class="w-3.5 h-3.5 text-amber-300"></i> Scan Barcode / QRIS
                        </button>
                    </div>

                    <select id="selectMetodePOS" name="metode_pos" class="w-full border border-slate-200 bg-white p-2 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Tunai">Tunai (Cash)</option>
                        <option value="QRIS">QRIS Instan (BCA/Dana/Gopay)</option>
                        <option value="DANA">DANA Dompet Digital</option>
                        <option value="ShopeePay">ShopeePay</option>
                        <option value="OVO">OVO Payment</option>
                        <option value="Transfer BCA">Transfer Bank BCA</option>
                        <option value="Saldo TokoKita">Saldo Dompet TokoKita</option>
                    </select>

                    <input type="hidden" id="hiddenRefPOS" name="ref_barcode_pos" value="">
                    <div id="badgeScannedPOS" class="hidden bg-emerald-100 text-emerald-800 p-2 rounded-xl text-[11px] font-bold flex items-center justify-between border border-emerald-300">
                        <span id="textScannedPOS" class="truncate"></span>
                        <button type="button" onclick="resetScanPOS()" class="text-rose-600 font-bold ml-1 hover:underline">×</button>
                    </div>
                </div>

                <!-- Kalkulasi Realtime -->
                <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Harga Satuan:</span>
                        <span id="labelHarga" class="font-bold text-slate-700">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Potongan Diskon:</span>
                        <span id="labelPotongan" class="font-bold text-rose-600">-Rp 0</span>
                    </div>
                    <div class="flex justify-between text-sm font-black text-slate-800 pt-1.5 border-t border-slate-200">
                        <span>Total Tagihan:</span>
                        <span id="labelTotal" class="text-emerald-600">Rp 0</span>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" name="bayar_instan"
                    class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-emerald-600/30 text-xs transition flex items-center justify-center gap-2 active:scale-[0.98]">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i> Proses Bayar Sekarang
                </button>
            </form>

        </div>

    </main>

    <!-- MODAL KAMERA SCANNER BARCODE PRODUK (MULTI-MODE: KAMERA / UPLOAD FOTO / GUN / DEMO) -->
    <div id="modalKameraProduk" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
            <!-- Header Modal -->
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-600/30 flex items-center justify-center text-emerald-400">
                        <i data-lucide="scan-barcode" class="w-4 h-4 animate-pulse"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-white">Scanner Barcode Produk Kasir</h3>
                        <p class="text-[11px] text-slate-400">Scan Barcode untuk Menambahkan Produk ke Transaksi</p>
                    </div>
                </div>
                <button type="button" onclick="tutupModalKameraProduk()" class="text-slate-400 hover:text-white transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4 text-xs">
                <!-- Tab Switcher -->
                <div class="flex items-center bg-slate-100 p-1 rounded-2xl font-bold">
                    <button type="button" id="tabKamPrd" onclick="pilihTabModalProduk('kam')" class="flex-1 py-1.5 rounded-xl bg-white text-emerald-700 shadow-sm flex items-center justify-center gap-1 transition">
                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Kamera
                    </button>
                    <button type="button" id="tabFilePrd" onclick="pilihTabModalProduk('file')" class="flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1 transition">
                        <i data-lucide="image" class="w-3.5 h-3.5"></i> Foto/File
                    </button>
                    <button type="button" id="tabGunPrd" onclick="pilihTabModalProduk('gun')" class="flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1 transition">
                        <i data-lucide="barcode" class="w-3.5 h-3.5"></i> Barcode Gun
                    </button>
                    <button type="button" id="tabSimPrd" onclick="pilihTabModalProduk('sim')" class="flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1 transition">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i> Demo
                    </button>
                </div>

                <!-- TAB 1: KAMERA SCANNER LANGSUNG -->
                <div id="conKamPrd" class="space-y-3">
                    <!-- Dropdown Pemilih Kamera Perangkat (Muncul jika ada lebih dari 1 kamera) -->
                    <div id="wrapKameraSelect" class="hidden flex items-center justify-between gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200">
                        <label class="text-[11px] font-bold text-slate-600 flex items-center gap-1 shrink-0">
                            <i data-lucide="video" class="w-3.5 h-3.5 text-indigo-600"></i> Kamera:
                        </label>
                        <select id="selectKameraDeviceProduk" onchange="gantiKameraDevice(this.value)" class="w-full bg-white text-slate-800 text-[11px] border border-slate-200 rounded-lg p-1.5 font-bold focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </select>
                    </div>

                    <!-- Kotak Layar Video Scanner -->
                    <div class="relative bg-slate-950 rounded-2xl overflow-hidden aspect-video min-h-[220px] max-h-[270px] flex items-center justify-center shadow-inner">
                        <div id="readerKameraProduk" class="w-full h-full flex items-center justify-center"></div>
                        <div class="scanning-laser"></div>
                        
                        <div id="phKameraProduk" class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 bg-slate-900/90 z-10 transition">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-2">
                                <i data-lucide="camera" class="w-6 h-6"></i>
                            </div>
                            <p class="font-bold text-white mb-0.5">Kamera Scanner Siap</p>
                            <p class="text-slate-400 text-[11px] mb-3">Klik tombol untuk menyalakan kamera komputer/laptop</p>
                            <button type="button" onclick="startKameraProduk()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold px-4 py-2 rounded-xl flex items-center gap-1.5 shadow-lg shadow-emerald-600/30 transition active:scale-95">
                                <i data-lucide="play" class="w-3.5 h-3.5"></i> Mulai Kamera
                            </button>
                        </div>
                    </div>

                    <!-- Kotak Notifikasi / Bantuan jika Kamera Bermasalah -->
                    <div id="errorKameraProduk" class="hidden bg-amber-50 border border-amber-300 text-amber-900 p-3 rounded-2xl space-y-2">
                        <div class="flex items-start gap-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                            <div>
                                <p class="font-bold text-xs" id="errorKameraProdukText">Tidak dapat mengakses kamera.</p>
                                <p class="text-[11px] text-amber-700 mt-1">
                                    💡 <strong>Tips Mengatasi:</strong><br>
                                    1. Pastikan izin kamera diizinkan di browser (klik tanda gembok 🔒 di sebelah address bar <code>localhost</code> → Izinkan Kamera).<br>
                                    2. Jika laptop tidak memiliki webcam, gunakan tab <strong>Foto/File</strong>, <strong>Barcode Gun</strong>, atau <strong>Demo</strong> di atas.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1 border-t border-amber-200">
                            <button type="button" onclick="startKameraProduk()" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-3 py-1 rounded-lg text-[10.5px] transition">
                                🔄 Coba Lagi
                            </button>
                            <button type="button" onclick="pilihTabModalProduk('file')" class="bg-white border border-amber-300 hover:bg-amber-100 text-amber-800 font-bold px-3 py-1 rounded-lg text-[10.5px] transition">
                                📁 Gunakan Upload Foto
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: UPLOAD FOTO / GAMBAR BARCODE -->
                <div id="conFilePrd" class="hidden space-y-3">
                    <div onclick="document.getElementById('fileBarcodeProduk').click()" 
                         class="border-2 border-dashed border-indigo-300 hover:border-indigo-600 bg-indigo-50/40 hover:bg-indigo-50/80 rounded-2xl p-6 text-center cursor-pointer transition group">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 text-indigo-600 flex items-center justify-center mx-auto mb-2 group-hover:scale-110 transition">
                            <i data-lucide="image-plus" class="w-6 h-6"></i>
                        </div>
                        <p class="font-extrabold text-sm text-slate-800">Klik untuk Memilih Foto / Gambar Barcode</p>
                        <p class="text-[11px] text-slate-400 mt-1">Mendukung file gambar PNG, JPG, JPEG, WEBP. Barcode langsung didekode & dimasukkan ke kasir otomatis!</p>
                        <input type="file" id="fileBarcodeProduk" accept="image/*" onchange="scanBarcodeDariFile(this.files)" class="hidden">
                    </div>
                </div>

                <!-- TAB 3: BARCODE GUN USB / SCANNER FISIK -->
                <div id="conGunPrd" class="hidden space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tembak Barcode Scanner Gun USB:</label>
                        <p class="text-[11px] text-slate-400 mb-2">Arahkan scanner gun fisik ke barcode barang, angka akan otomatis terisi dan tekan Enter.</p>
                        <div class="relative">
                            <input type="text" id="inpGunPrd" placeholder="Arahkan barcode gun ke sini..." 
                                class="w-full border-2 border-emerald-400 p-2.5 rounded-xl font-mono text-sm font-bold bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>

                <!-- TAB 4: UJI DEMO 1-KLIK -->
                <div id="conSimPrd" class="hidden space-y-2">
                    <p class="font-bold text-slate-600 text-[11px]">Klik salah satu produk demo untuk simulasi scan instan:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button type="button" onclick="tutupModalKameraProduk(); scanBarcodeDanTambah('89900000001')" 
                            class="p-2.5 border border-slate-200 hover:border-indigo-500 rounded-xl text-left bg-white shadow-sm hover:shadow transition flex items-center justify-between">
                            <div>
                                <p class="font-bold text-slate-800">🌾 Beras Premium 5kg</p>
                                <p class="text-[10px] font-mono text-slate-400">89900000001</p>
                            </div>
                            <span class="text-xs font-black text-emerald-600">Rp 75.000</span>
                        </button>
                        <button type="button" onclick="tutupModalKameraProduk(); scanBarcodeDanTambah('89900000002')" 
                            class="p-2.5 border border-slate-200 hover:border-indigo-500 rounded-xl text-left bg-white shadow-sm hover:shadow transition flex items-center justify-between">
                            <div>
                                <p class="font-bold text-slate-800">🌻 Minyak Goreng 2L</p>
                                <p class="text-[10px] font-mono text-slate-400">89900000002</p>
                            </div>
                            <span class="text-xs font-black text-emerald-600">Rp 35.000</span>
                        </button>
                        <button type="button" onclick="tutupModalKameraProduk(); scanBarcodeDanTambah('89900000003')" 
                            class="p-2.5 border border-slate-200 hover:border-indigo-500 rounded-xl text-left bg-white shadow-sm hover:shadow transition flex items-center justify-between">
                            <div>
                                <p class="font-bold text-slate-800">☕ Kopi Arabika 250g</p>
                                <p class="text-[10px] font-mono text-slate-400">89900000003</p>
                            </div>
                            <span class="text-xs font-black text-emerald-600">Rp 45.000</span>
                        </button>
                        <button type="button" onclick="tutupModalKameraProduk(); scanBarcodeDanTambah('89900000004')" 
                            class="p-2.5 border border-slate-200 hover:border-indigo-500 rounded-xl text-left bg-white shadow-sm hover:shadow transition flex items-center justify-between">
                            <div>
                                <p class="font-bold text-slate-800">📓 Buku Tulis Pak</p>
                                <p class="text-[10px] font-mono text-slate-400">89900000004</p>
                            </div>
                            <span class="text-xs font-black text-emerald-600">Rp 38.000</span>
                        </button>
                        <button type="button" onclick="tutupModalKameraProduk(); scanBarcodeDanTambah('89900000005')" 
                            class="p-2.5 border border-slate-200 hover:border-indigo-500 rounded-xl text-left bg-white shadow-sm hover:shadow transition flex items-center justify-between sm:col-span-2">
                            <div>
                                <p class="font-bold text-slate-800">💧 AQUAVIVA Botol</p>
                                <p class="text-[10px] font-mono text-slate-400">89900000005</p>
                            </div>
                            <span class="text-xs font-black text-emerald-600">Rp 3.000</span>
                        </button>
                    </div>
                </div>

                <!-- Footer Informasi -->
                <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 text-slate-600 flex items-center gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                    <p class="text-[11px] leading-tight">Saat barcode berhasil terdeteksi, sistem berbunyi 'beep' dan produk langsung terpilih di kasir.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL SCANNER BARCODE POS -->
    <div id="modalScannerPOS" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600/30 flex items-center justify-center text-indigo-400">
                        <i data-lucide="scan" class="w-4 h-4 animate-pulse"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-white">Scanner Barcode Pembayaran POS</h3>
                        <p class="text-[11px] text-slate-400">Scan QRIS / E-Wallet / Barcode Pelanggan</p>
                    </div>
                </div>
                <button type="button" onclick="tutupModalScannerPOS()" class="text-slate-400 hover:text-white">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
                <!-- Tab Switcher -->
                <div class="flex items-center bg-slate-100 p-1 rounded-2xl font-bold">
                    <button type="button" id="tabKamPOS" onclick="pilihTabPOS('kam')" class="flex-1 py-1.5 rounded-xl bg-white text-indigo-700 shadow-sm flex items-center justify-center gap-1">
                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Kamera
                    </button>
                    <button type="button" id="tabGunPOS" onclick="pilihTabPOS('gun')" class="flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1">
                        <i data-lucide="barcode" class="w-3.5 h-3.5"></i> Barcode Gun
                    </button>
                    <button type="button" id="tabSimPOS" onclick="pilihTabPOS('sim')" class="flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-500"></i> Uji Demo
                    </button>
                </div>

                <!-- Kamera -->
                <div id="conKamPOS" class="space-y-2">
                    <div class="relative bg-slate-900 rounded-2xl overflow-hidden aspect-video max-h-[240px] flex items-center justify-center">
                        <div id="readerPOS" class="w-full h-full flex items-center justify-center"></div>
                        <div class="scanning-laser"></div>
                        <div id="phPOS" class="absolute inset-0 flex flex-col items-center justify-center text-center p-3 bg-slate-900/90 z-10">
                            <i data-lucide="camera" class="w-8 h-8 text-slate-400 mb-1"></i>
                            <p class="font-bold text-white mb-2">Kamera Scanner Siap</p>
                            <button type="button" onclick="startKamPOS()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-3 py-1.5 rounded-xl">
                                Nyalakan Kamera
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Barcode Gun -->
                <div id="conGunPOS" class="hidden space-y-2 bg-slate-50 p-3 rounded-2xl border border-slate-200">
                    <label class="block font-bold text-slate-700">Tembak Barcode Gun USB:</label>
                    <input type="text" id="inpGunPOS" placeholder="Arahkan barcode gun ke sini..." class="w-full border-2 border-indigo-300 p-2 rounded-xl font-mono font-bold">
                </div>

                <!-- Demo -->
                <div id="conSimPOS" class="hidden grid grid-cols-2 gap-2">
                    <button type="button" onclick="prosesScanPOS('QRIS-NMID-00020101021226590014ID-BCA')" class="p-2 border rounded-xl hover:border-indigo-500 text-left bg-white shadow-sm font-bold">QRIS BCA</button>
                    <button type="button" onclick="prosesScanPOS('DANA-PAY-081234567890')" class="p-2 border rounded-xl hover:border-blue-500 text-left bg-white shadow-sm font-bold">DANA QR</button>
                    <button type="button" onclick="prosesScanPOS('SPAY-TRX-883921')" class="p-2 border rounded-xl hover:border-amber-500 text-left bg-white shadow-sm font-bold">ShopeePay</button>
                    <button type="button" onclick="prosesScanPOS('OVO-CASH-PAY-88391')" class="p-2 border rounded-xl hover:border-purple-500 text-left bg-white shadow-sm font-bold">OVO Pay</button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        function filterProdukPOS() {
            const query = document.getElementById('cariProdukPOS').value.toLowerCase();
            const cards = document.querySelectorAll('.pos-card');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function pilihProduk(id, nama, harga) {
            const select = document.getElementById('selectProduk');
            select.value = id;
            onProdukChange();
        }

        function onProdukChange() {
            hitungTotalExpress();
        }

        function hitungTotalExpress() {
            const select = document.getElementById('selectProduk');
            const selectedOpt = select.options[select.selectedIndex];
            const harga = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-harga')) || 0 : 0;
            const qty = parseInt(document.getElementById('inputQty').value) || 1;
            const diskonPersen = parseFloat(document.getElementById('inputDiskon').value) || 0;

            const subtotal = harga * qty;
            const potongan = subtotal * (diskonPersen / 100);
            const total = subtotal - potongan;

            const fmt = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));

            document.getElementById('labelHarga').innerText = fmt(harga);
            document.getElementById('labelPotongan').innerText = '-' + fmt(potongan);
            document.getElementById('labelTotal').innerText = fmt(total);
        }

        // ==============================================================
        // SCANNER BARCODE PRODUK (MULTI-CAMERA & FILE SCANNER BULLETPROOF)
        // ==============================================================
        let qrProduk = null;
        let isCamProdukRunning = false;
        let daftarKameraProduk = [];
        let currentKameraIdProduk = null;

        function bukaModalKameraProduk() {
            const m = document.getElementById('modalKameraProduk');
            m.classList.remove('hidden');
            m.classList.add('flex');
            if (typeof refreshIcons === 'function') refreshIcons();
            else if (window.lucide) lucide.createIcons();
            pilihTabModalProduk('kam');
        }

        function tutupModalKameraProduk() {
            stopKameraProduk();
            const m = document.getElementById('modalKameraProduk');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }

        function pilihTabModalProduk(tab) {
            ['kam', 'file', 'gun', 'sim'].forEach(t => {
                const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1) + 'Prd');
                const con = document.getElementById('con' + t.charAt(0).toUpperCase() + t.slice(1) + 'Prd');
                if (btn) btn.className = 'flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1 transition';
                if (con) con.classList.add('hidden');
            });

            const selBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Prd');
            const selCon = document.getElementById('con' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Prd');
            if (selBtn) selBtn.className = 'flex-1 py-1.5 rounded-xl bg-white text-emerald-700 shadow-sm flex items-center justify-center gap-1 transition';
            if (selCon) selCon.classList.remove('hidden');

            if (tab === 'kam') {
                startKameraProduk();
            } else {
                stopKameraProduk();
                if (tab === 'gun') {
                    setTimeout(() => {
                        const ig = document.getElementById('inpGunPrd');
                        if (ig) ig.focus();
                    }, 100);
                }
            }
            if (typeof refreshIcons === 'function') refreshIcons();
            else if (window.lucide) lucide.createIcons();
        }

        function startKameraProduk() {
            const errBox = document.getElementById('errorKameraProduk');
            if (errBox) errBox.classList.add('hidden');

            if (typeof Html5Qrcode === 'undefined') {
                tanganiErrorKamera("Library Html5Qrcode belum selesai dimuat. Silakan muat ulang halaman.");
                return;
            }

            if (!qrProduk) {
                qrProduk = new Html5Qrcode("readerKameraProduk");
            }

            // Dapatkan daftar video input devices (Webcam / HP / External)
            Html5Qrcode.getCameras().then(devices => {
                daftarKameraProduk = devices;
                const wrapSel = document.getElementById('wrapKameraSelect');
                const sel = document.getElementById('selectKameraDeviceProduk');

                if (devices && devices.length > 0) {
                    if (sel) {
                        sel.innerHTML = '';
                        devices.forEach((d, idx) => {
                            const opt = document.createElement('option');
                            opt.value = d.id;
                            opt.text = d.label || ('Kamera ' + (idx + 1));
                            sel.appendChild(opt);
                        });
                    }
                    if (wrapSel && devices.length > 1) {
                        wrapSel.classList.remove('hidden');
                    }

                    // Prioritaskan kamera belakang jika ada
                    let targetCamId = currentKameraIdProduk || devices[0].id;
                    if (!currentKameraIdProduk) {
                        for (let d of devices) {
                            const lbl = (d.label || '').toLowerCase();
                            if (lbl.includes('back') || lbl.includes('belakang') || lbl.includes('rear') || lbl.includes('environment')) {
                                targetCamId = d.id;
                                break;
                            }
                        }
                    }
                    if (sel) sel.value = targetCamId;
                    currentKameraIdProduk = targetCamId;
                    jalankanStreamKamera(targetCamId);
                } else {
                    // Coba fallback tanpa deviceId spesifik (kompatibel webcam laptop biasa)
                    jalankanStreamKamera({ facingMode: "user" });
                }
            }).catch(err => {
                console.warn("getCameras gagal, coba fallback { video: true }:", err);
                jalankanStreamKamera({ video: true });
            });
        }

        function gantiKameraDevice(deviceId) {
            currentKameraIdProduk = deviceId;
            jalankanStreamKamera(deviceId);
        }

        function jalankanStreamKamera(cameraConfig) {
            if (!qrProduk) qrProduk = new Html5Qrcode("readerKameraProduk");
            
            if (isCamProdukRunning) {
                qrProduk.stop().then(() => {
                    isCamProdukRunning = false;
                    eksekusiStartKamera(cameraConfig);
                }).catch(() => {
                    eksekusiStartKamera(cameraConfig);
                });
            } else {
                eksekusiStartKamera(cameraConfig);
            }
        }

        function eksekusiStartKamera(cameraConfig) {
            const ph = document.getElementById('phKameraProduk');
            const errBox = document.getElementById('errorKameraProduk');
            if (errBox) errBox.classList.add('hidden');

            const scanConfig = {
                fps: 15,
                qrbox: (w, h) => {
                    const min = Math.min(w, h);
                    return { width: Math.floor(min * 0.85), height: Math.floor(min * 0.55) };
                },
                aspectRatio: 1.333333
            };

            qrProduk.start(
                cameraConfig,
                scanConfig,
                (decodedText) => {
                    tutupModalKameraProduk();
                    scanBarcodeDanTambah(decodedText);
                },
                () => {}
            ).then(() => {
                isCamProdukRunning = true;
                if (ph) ph.classList.add('hidden');
            }).catch(err => {
                console.error("Gagal start kamera produk:", err);
                isCamProdukRunning = false;
                
                // Fallback kedua jika constraint spesifik ditolak browser
                if (typeof cameraConfig === 'object') {
                    qrProduk.start(
                        { video: true },
                        scanConfig,
                        (decodedText) => {
                            tutupModalKameraProduk();
                            scanBarcodeDanTambah(decodedText);
                        },
                        () => {}
                    ).then(() => {
                        isCamProdukRunning = true;
                        if (ph) ph.classList.add('hidden');
                    }).catch(err2 => {
                        tanganiErrorKamera(err2);
                    });
                } else {
                    tanganiErrorKamera(err);
                }
            });
        }

        function stopKameraProduk() {
            if (qrProduk && isCamProdukRunning) {
                qrProduk.stop().then(() => {
                    isCamProdukRunning = false;
                    const ph = document.getElementById('phKameraProduk');
                    if (ph) ph.classList.remove('hidden');
                }).catch(err => console.error(err));
            }
        }

        function tanganiErrorKamera(err) {
            const ph = document.getElementById('phKameraProduk');
            if (ph) ph.classList.remove('hidden');

            let pesan = "Tidak dapat mengaktifkan kamera.";
            const errStr = (err && err.toString ? err.toString() : '') + ' ' + (err && err.name ? err.name : '');

            if (errStr.includes("NotAllowedError") || errStr.includes("Permission") || errStr.includes("denied")) {
                pesan = "Izin Kamera Diblokir Browser! Klik tanda gembok 🔒 di samping 'localhost' pada address bar browser Anda, ubah Camera menjadi 'Allow', lalu klik Coba Lagi.";
            } else if (errStr.includes("NotFoundError") || errStr.includes("DevicesNotFoundError")) {
                pesan = "Kamera / Webcam tidak terdeteksi pada perangkat ini. Silakan gunakan tab 'Foto/File' atau 'Barcode Gun'.";
            } else if (errStr.includes("NotReadableError") || errStr.includes("TrackStartError")) {
                pesan = "Kamera sedang digunakan aplikasi lain (Zoom/Meet/Kamera Windows). Tutup aplikasi tersebut dan coba lagi.";
            } else if (errStr.includes("OverconstrainedError")) {
                pesan = "Resolusi kamera tidak mendukung constraint. Mengalihkan ke mode kamera standar...";
            }

            const errBox = document.getElementById('errorKameraProduk');
            const errTxt = document.getElementById('errorKameraProdukText');
            if (errBox && errTxt) {
                errTxt.innerText = pesan;
                errBox.classList.remove('hidden');
            }
        }

        function scanBarcodeDariFile(files) {
            if (!files || files.length === 0) return;
            const file = files[0];

            if (!qrProduk) {
                qrProduk = new Html5Qrcode("readerKameraProduk");
            }

            tampilkanToast("🔍 Membaca barcode dari gambar...", "info");
            qrProduk.scanFile(file, true)
                .then(decodedText => {
                    tutupModalKameraProduk();
                    scanBarcodeDanTambah(decodedText);
                })
                .catch(err => {
                    console.error("Gagal scan file:", err);
                    tampilkanToast("Barcode tidak terbaca pada foto. Pastikan foto fokus dan jelas.", "warning");
                    tanganiErrorKamera("Barcode tidak terdeteksi pada gambar. Pastikan gambar cukup terang dan barcode terlihat jelas.");
                });
        }

        function submitManualBarcodeProduk() {
            const inp = document.getElementById('inputBarcodeProduk');
            const val = inp ? inp.value.trim() : '';
            if (val !== '') {
                scanBarcodeDanTambah(val);
                inp.value = '';
            }
        }

        // FUNGSI UTAMA: Scan Barcode via API & Tambahkan ke Kasir POS
        function scanBarcodeDanTambah(barcode) {
            if (!barcode) return;
            
            const statBox = document.getElementById('statusScanProduk');
            const iconSpan = document.getElementById('iconStatusScan');
            const pesanSpan = document.getElementById('pesanStatusScan');

            if (statBox) {
                statBox.className = "mt-3 p-2.5 rounded-xl text-xs font-bold flex items-center justify-between border bg-indigo-50 border-indigo-200 text-indigo-900";
                if (iconSpan) iconSpan.innerHTML = '⏳';
                if (pesanSpan) pesanSpan.innerText = 'Mencari barcode ' + barcode + '...';
                statBox.classList.remove('hidden');
            }

            fetch('api_scan.php?barcode=' + encodeURIComponent(barcode))
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.found) {
                        const prod = data.produk;
                        playScannerBeep();

                        // Cek apakah produk ini yang sedang terpilih di selectProduk
                        const select = document.getElementById('selectProduk');
                        const qtyInput = document.getElementById('inputQty');
                        
                        if (select && select.value == prod.id_produk) {
                            // Jika produk sama, tambahkan kuantitas + 1
                            const currentQty = parseInt(qtyInput.value) || 1;
                            if (currentQty + 1 <= prod.stok) {
                                qtyInput.value = currentQty + 1;
                            } else {
                                tampilkanToast("Stok " + prod.nama_produk + " tersisa " + prod.stok + " unit", "warning");
                            }
                        } else if (select) {
                            // Pilih produk di dropdown form kasir
                            select.value = prod.id_produk;
                            if (qtyInput) qtyInput.value = 1;
                        }

                        // Hitung ulang total transaksi POS
                        hitungTotalExpress();

                        // Tampilkan status sukses
                        if (statBox) {
                            statBox.className = "mt-3 p-2.5 rounded-xl text-xs font-bold flex items-center justify-between border bg-emerald-50 border-emerald-300 text-emerald-900 shadow-sm";
                            if (iconSpan) iconSpan.innerHTML = '✅';
                            if (pesanSpan) pesanSpan.innerHTML = 'Produk <strong>' + prod.nama_produk + '</strong> (' + prod.harga_format + ') berhasil dimasukkan!';
                        }
                        
                        tampilkanToast("✅ " + prod.nama_produk + " (" + prod.harga_format + ") dimasukkan!", "success");

                        // Kembalikan fokus ke input barcode
                        const inp = document.getElementById('inputBarcodeProduk');
                        if (inp) {
                            inp.value = '';
                            inp.focus();
                        }
                    } else {
                        if (statBox) {
                            statBox.className = "mt-3 p-2.5 rounded-xl text-xs font-bold flex items-center justify-between border bg-rose-50 border-rose-300 text-rose-900";
                            if (iconSpan) iconSpan.innerHTML = '⚠️';
                            if (pesanSpan) pesanSpan.innerText = data.pesan || ('Barcode ' + barcode + ' tidak ditemukan!');
                        }
                        tampilkanToast("Barcode tidak ditemukan di sistem!", "error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (statBox) {
                        statBox.className = "mt-3 p-2.5 rounded-xl text-xs font-bold flex items-center justify-between border bg-rose-50 border-rose-300 text-rose-900";
                        if (iconSpan) iconSpan.innerHTML = '❌';
                        if (pesanSpan) pesanSpan.innerText = 'Gagal menghubungi server scanner.';
                    }
                });
        }

        // ===================================
        // SCANNER POS (PEMBAYARAN QRIS / EWALLET)
        // ===================================
        let qrPOS = null;
        let isCamPOSRunning = false;

        function bukaModalScannerPOS() {
            const m = document.getElementById('modalScannerPOS');
            m.classList.remove('hidden');
            m.classList.add('flex');
            pilihTabPOS('kam');
            refreshIcons();
        }

        function tutupModalScannerPOS() {
            stopKamPOS();
            const m = document.getElementById('modalScannerPOS');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }

        function pilihTabPOS(tab) {
            ['kam', 'gun', 'sim'].forEach(t => {
                const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1) + 'POS');
                const con = document.getElementById('con' + t.charAt(0).toUpperCase() + t.slice(1) + 'POS');
                if (btn) btn.className = 'flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1';
                if (con) con.classList.add('hidden');
            });
            const selBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'POS');
            const selCon = document.getElementById('con' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'POS');
            if (selBtn) selBtn.className = 'flex-1 py-1.5 rounded-xl bg-white text-indigo-700 shadow-sm flex items-center justify-center gap-1';
            if (selCon) selCon.classList.remove('hidden');

            if (tab === 'gun') {
                stopKamPOS();
                setTimeout(() => {
                    const ig = document.getElementById('inpGunPOS');
                    if (ig) ig.focus();
                }, 100);
            } else if (tab === 'sim') {
                stopKamPOS();
            } else if (tab === 'kam') {
                startKamPOS();
            }
            refreshIcons();
        }

        function startKamPOS() {
            if (typeof Html5Qrcode === 'undefined') return;
            const ph = document.getElementById('phPOS');
            if (!qrPOS) qrPOS = new Html5Qrcode("readerPOS");
            
            const scanConfig = { fps: 15, qrbox: { width: 220, height: 220 } };

            Html5Qrcode.getCameras().then(devices => {
                let targetId = (devices && devices.length > 0) ? devices[0].id : { video: true };
                qrPOS.start(targetId, scanConfig, (txt) => {
                    prosesScanPOS(txt);
                }, () => {}).then(() => {
                    isCamPOSRunning = true;
                    if (ph) ph.classList.add('hidden');
                }).catch(() => {
                    // Fallback video true
                    qrPOS.start({ video: true }, scanConfig, (txt) => {
                        prosesScanPOS(txt);
                    }, () => {}).then(() => {
                        isCamPOSRunning = true;
                        if (ph) ph.classList.add('hidden');
                    }).catch(e => console.error("Kamera POS gagal:", e));
                });
            }).catch(() => {
                qrPOS.start({ video: true }, scanConfig, (txt) => {
                    prosesScanPOS(txt);
                }, () => {}).then(() => {
                    isCamPOSRunning = true;
                    if (ph) ph.classList.add('hidden');
                }).catch(e => console.error("Kamera POS fallback gagal:", e));
            });
        }

        function stopKamPOS() {
            if (qrPOS && isCamPOSRunning) {
                qrPOS.stop().then(() => {
                    isCamPOSRunning = false;
                    const ph = document.getElementById('phPOS');
                    if (ph) ph.classList.remove('hidden');
                }).catch(e => console.error(e));
            }
        }

        function prosesScanPOS(txt) {
            if (!txt) return;
            playScannerBeep();
            const p = parsePaymentBarcode(txt);
            document.getElementById('selectMetodePOS').value = p.metode;
            document.getElementById('hiddenRefPOS').value = p.raw;

            const badge = document.getElementById('badgeScannedPOS');
            const textBadge = document.getElementById('textScannedPOS');
            badge.classList.remove('hidden');
            textBadge.innerText = '✅ ' + p.label + ' (' + p.refCode + ')';

            tutupModalScannerPOS();
            tampilkanToast("✅ Barcode Pembayaran: " + p.label, "success");
        }

        function resetScanPOS() {
            document.getElementById('selectMetodePOS').value = 'Tunai';
            document.getElementById('hiddenRefPOS').value = '';
            document.getElementById('badgeScannedPOS').classList.add('hidden');
            tampilkanToast("Metode di-reset ke Tunai", "info");
        }

        document.addEventListener('DOMContentLoaded', () => {
            hitungTotalExpress();
            
            // Listener Barcode Gun di Terminal POS
            const ib = document.getElementById('inputBarcodeProduk');
            if (ib) {
                ib.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitManualBarcodeProduk();
                    }
                });
            }

            // Listener Barcode Gun di Modal Produk
            const igPrd = document.getElementById('inpGunPrd');
            if (igPrd) {
                igPrd.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (igPrd.value.trim() !== '') {
                            const val = igPrd.value.trim();
                            igPrd.value = '';
                            tutupModalKameraProduk();
                            scanBarcodeDanTambah(val);
                        }
                    }
                });
            }

            // Listener Barcode Gun di Modal Pembayaran
            const ig = document.getElementById('inpGunPOS');
            if (ig) {
                ig.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (ig.value.trim() !== '') {
                            prosesScanPOS(ig.value.trim());
                            ig.value = '';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
