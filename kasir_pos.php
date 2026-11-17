<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif = "";
$error = "";
$last_trx_id = 0;

// Transaksi Instan 1 Klik via Stored Procedure sp_TambahTransaksiBaru
if (isset($_POST['bayar_instan'])) {
    $id_pel = (int)$_POST['id_pelanggan'];
    $id_prd = (int)$_POST['id_produk'];
    $qty    = (int)$_POST['jumlah'];
    $disc   = (float)$_POST['diskon'];

    if (mysqli_query($conn, "CALL sp_TambahTransaksiBaru($id_pel, $id_prd, $qty, $disc)")) {
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
        
        $q_last = mysqli_query($conn, "SELECT id_transaksi FROM tbl_transaksi ORDER BY id_transaksi DESC LIMIT 1");
        if ($r_last = mysqli_fetch_assoc($q_last)) {
            $last_trx_id = (int)$r_last['id_transaksi'];
        }

        $notif = "Transaksi Kasir POS Berhasil! Stok produk otomatis terpotong oleh Trigger Database.";
    } else {
        $error = "Gagal memproses transaksi: " . mysqli_error($conn);
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
    <!-- Library Barcode & QR Code Scanner HTML5 -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
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

        // ========================
        // SCANNER POS
        // ========================
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
                document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1) + 'POS').className = 'flex-1 py-1.5 rounded-xl text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1';
                document.getElementById('con' + t.charAt(0).toUpperCase() + t.slice(1) + 'POS').classList.add('hidden');
            });
            const selBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'POS');
            const selCon = document.getElementById('con' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'POS');
            selBtn.className = 'flex-1 py-1.5 rounded-xl bg-white text-indigo-700 shadow-sm flex items-center justify-center gap-1';
            selCon.classList.remove('hidden');

            if (tab === 'gun') {
                stopKamPOS();
                setTimeout(() => document.getElementById('inpGunPOS').focus(), 100);
            } else if (tab === 'sim') {
                stopKamPOS();
            }
            refreshIcons();
        }

        function startKamPOS() {
            if (typeof Html5Qrcode === 'undefined') return;
            document.getElementById('phPOS').classList.add('hidden');
            if (!qrPOS) qrPOS = new Html5Qrcode("readerPOS");
            qrPOS.start({ facingMode: "environment" }, { fps: 15, qrbox: { width: 220, height: 220 } }, (txt) => {
                prosesScanPOS(txt);
            }, () => {}).then(() => { isCamPOSRunning = true; });
        }

        function stopKamPOS() {
            if (qrPOS && isCamPOSRunning) {
                qrPOS.stop().then(() => {
                    isCamPOSRunning = false;
                    document.getElementById('phPOS').classList.remove('hidden');
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
