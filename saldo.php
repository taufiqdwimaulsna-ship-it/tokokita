<?php
/**
 * TokoKita - Dompet Digital & Manajemen Saldo Member
 * File: saldo.php
 */
require_once 'functions.php';
cekLogin();

$notif = "";
$error = "";

// Ambil daftar pelanggan untuk dropdown pilihan member (Khusus Kasir & Admin)
$list_pelanggan = [];
if (!isPelanggan()) {
    $q_pelanggan = mysqli_query($conn, "SELECT * FROM view_pelanggan ORDER BY nama_pelanggan ASC");
    $list_pelanggan = mysqli_fetch_all($q_pelanggan, MYSQLI_ASSOC);
}

// Tentukan ID Pelanggan aktif
$id_pel_aktif = 0;
if (isPelanggan()) {
    // Kunci ID ke akun pelanggan yang sedang login (Pelanggan hanya mengisi saldonya sendiri)
    $nama_user = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);
    $qp_me = mysqli_query($conn, "SELECT id_pelanggan FROM tbl_pelanggan WHERE nama_pelanggan = '$nama_user' LIMIT 1");
    if ($rp_me = mysqli_fetch_assoc($qp_me)) {
        $id_pel_aktif = (int)$rp_me['id_pelanggan'];
    }
    if ($id_pel_aktif === 0) {
        $qp_any = mysqli_query($conn, "SELECT id_pelanggan FROM tbl_pelanggan LIMIT 1");
        if ($rp_any = mysqli_fetch_assoc($qp_any)) {
            $id_pel_aktif = (int)$rp_any['id_pelanggan'];
        }
    }
} else {
    $id_pel_aktif = isset($_GET['id_pelanggan']) ? (int)$_GET['id_pelanggan'] : 0;
    if ($id_pel_aktif === 0 && !empty($list_pelanggan)) {
        $id_pel_aktif = (int)$list_pelanggan[0]['id_pelanggan'];
    }
}

// Proses Form Isi Saldo (Top-Up)
if (isset($_POST['proses_topup'])) {
    $id_pel  = isPelanggan() ? $id_pel_aktif : (int)$_POST['id_pelanggan'];
    $nominal = (float)$_POST['nominal'];
    $metode  = mysqli_real_escape_string($conn, $_POST['metode']);

    if ($nominal < 10000) {
        $error = "Minimal pengisian saldo adalah Rp 10.000!";
    } else {
        $q_insert = mysqli_query($conn, "INSERT INTO tbl_topup (id_pelanggan, nominal, metode, status) VALUES ($id_pel, $nominal, '$metode', 'Berhasil')");
        if ($q_insert) {
            $id_tp = mysqli_insert_id($conn);
            $pelaku = isPelanggan() ? "Pelanggan {$_SESSION['nama_lengkap']}" : "Petugas Kasir/Admin";
            mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_topup', 'TOPUP MEMBER', '$pelaku melakukan pengisian saldo #TP-$id_tp pelanggan #$id_pel nominal $nominal via $metode')");
            $notif = "Pengisian saldo member sebesar " . rupiah($nominal) . " berhasil melalui $metode! Saldo langsung bertambah.";
            $id_pel_aktif = $id_pel;
        } else {
            $error = "Gagal memproses pengisian saldo: " . mysqli_error($conn);
        }
    }
}

// Ambil info pelanggan aktif
$q_info_pel = mysqli_query($conn, "
    SELECT p.*, 
           COALESCE(SUM(tp.nominal), 0) AS total_topup,
           COUNT(tp.id_topup) AS jml_topup
    FROM tbl_pelanggan p
    LEFT JOIN tbl_topup tp ON p.id_pelanggan = tp.id_pelanggan
    WHERE p.id_pelanggan = $id_pel_aktif
    GROUP BY p.id_pelanggan
");
$info_pel = mysqli_fetch_assoc($q_info_pel);

// Ambil data belanja dari view_total_belanja
$q_belanja = mysqli_query($conn, "
    SELECT total_belanja, KategoriBelanja(total_belanja) AS level_member
    FROM view_total_belanja 
    WHERE id_pelanggan = $id_pel_aktif
");
$row_belanja = mysqli_fetch_assoc($q_belanja);
$total_belanja_pel = $row_belanja ? (float)$row_belanja['total_belanja'] : 0;
$level_member = $row_belanja ? $row_belanja['level_member'] : 'Pelanggan Reguler';

// Saldo saat ini = Total Topup
$saldo_tersedia = $info_pel ? (float)$info_pel['total_topup'] : 0;

// Riwayat Top-up Pelanggan Aktif atau Keseluruhan
$filter_mode = isPelanggan() ? 'member' : (isset($_GET['filter']) ? $_GET['filter'] : 'member');
if ($filter_mode === 'all' && !isPelanggan()) {
    $q_riwayat = mysqli_query($conn, "
        SELECT tp.*, p.nama_pelanggan, p.kota
        FROM tbl_topup tp
        JOIN tbl_pelanggan p ON tp.id_pelanggan = p.id_pelanggan
        ORDER BY tp.id_topup DESC
        LIMIT 50
    ");
} else {
    $q_riwayat = mysqli_query($conn, "
        SELECT tp.*, p.nama_pelanggan, p.kota
        FROM tbl_topup tp
        JOIN tbl_pelanggan p ON tp.id_pelanggan = p.id_pelanggan
        WHERE tp.id_pelanggan = $id_pel_aktif
        ORDER BY tp.id_topup DESC
        LIMIT 50
    ");
}

// Statistik Global Saldo Sistem
$stat_global = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) AS total_dana, COUNT(*) AS total_trx FROM tbl_topup"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dompet Saldo Member - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Notifikasi -->
        <?php if ($notif): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-semibold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                    <span><?= $notif ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl text-sm font-semibold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
                    <span><?= $error ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <!-- Bar Atas: Selector Member & Informasi Akun -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-600/20">
                    <i data-lucide="wallet-cards" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-lg md:text-xl font-black text-slate-800">Dompet Digital TokoKita Pay</h1>
                    <p class="text-xs text-slate-500">Kelola Saldo, Top-Up Multi-Payment, dan Riwayat Mutasi Dompet Member</p>
                </div>
            </div>

            <!-- Pilih Member -->
            <?php if (isPelanggan()): ?>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-500">Akun Member:</span>
                    <span class="bg-indigo-50 border border-indigo-200 text-indigo-700 font-extrabold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="user-check" class="w-3.5 h-3.5 text-indigo-600"></i>
                        <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
                    </span>
                </div>
            <?php else: ?>
                <form method="GET" class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-600 hidden sm:inline">Pilih Member:</label>
                    <select name="id_pelanggan" onchange="this.form.submit()" class="border border-slate-300 bg-slate-50 px-3 py-2 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($list_pelanggan as $lp): ?>
                            <option value="<?= $lp['id_pelanggan'] ?>" <?= $lp['id_pelanggan'] == $id_pel_aktif ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lp['nama_pelanggan']) ?> (<?= htmlspecialchars($lp['kota']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_mode) ?>">
                </form>
            <?php endif; ?>
        </div>

        <!-- Kartu Saldo & Ringkasan -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Virtual Card Dompet TokoKita -->
            <div class="bg-gradient-to-br from-indigo-700 via-indigo-800 to-purple-900 text-white p-6 rounded-3xl shadow-xl flex flex-col justify-between relative overflow-hidden">
                <!-- Background pattern -->
                <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-white/10 rounded-full blur-2xl"></div>
                <div class="absolute right-4 top-4 text-white/20"><i data-lucide="nfc" class="w-8 h-8"></i></div>

                <div class="space-y-1 relative z-10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold tracking-wider uppercase text-indigo-200">TokoKita Pay Member</span>
                        <span class="bg-white/20 text-white text-[10px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm">
                            <?= htmlspecialchars($level_member) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-300 pt-3">Saldo Dompet Tersedia</p>
                    <h2 class="text-3xl font-black tracking-tight text-white"><?= rupiah($saldo_tersedia) ?></h2>
                </div>

                <div class="pt-6 relative z-10 border-t border-white/20 mt-4 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] text-indigo-200 uppercase font-bold">Pemilik Akun</p>
                        <p class="text-xs font-bold text-white"><?= htmlspecialchars($info_pel['nama_pelanggan'] ?? 'Member') ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-indigo-200 uppercase font-bold">Kota Member</p>
                        <p class="text-xs font-bold text-white"><?= htmlspecialchars($info_pel['kota'] ?? '-') ?></p>
                    </div>
                </div>
            </div>

            <!-- Kartu Statistik Aktivitas Member -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 text-indigo-600"></i> Statistik Member
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center text-xs pb-2 border-b border-slate-100">
                            <span class="text-slate-500">Frekuensi Topup</span>
                            <span class="font-bold text-slate-800"><?= $info_pel['jml_topup'] ?? 0 ?> Kali</span>
                        </div>
                        <div class="flex justify-between items-center text-xs pb-2 border-b border-slate-100">
                            <span class="text-slate-500">Total Akumulasi Topup</span>
                            <span class="font-bold text-emerald-600"><?= rupiah($saldo_tersedia) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs pb-2 border-b border-slate-100">
                            <span class="text-slate-500">Total Belanja Toko</span>
                            <span class="font-bold text-indigo-600"><?= rupiah($total_belanja_pel) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Kategori Loyalitas</span>
                            <span class="font-bold text-purple-600"><?= htmlspecialchars($level_member) ?></span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 mt-2">
                    <button type="button" onclick="document.getElementById('inputNominal').focus();" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold py-2 rounded-xl text-xs transition flex items-center justify-center gap-1.5">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Isi Saldo Sekarang
                    </button>
                </div>
            </div>

            <!-- Kartu Info Saldo TokoKita Pay -->
            <?php if (isPelanggan()): ?>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-amber-500"></i> Keuntungan Saldo TokoKita Pay
                    </h3>
                    <div class="space-y-2.5 text-xs text-slate-600">
                        <div class="flex items-start gap-2">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                            <span>Bebas biaya admin untuk belanja semua produk katalog Mall.</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                            <span>Checkout instan 1-klik tanpa antre verifikasi manual.</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                            <span>Meningkatkan loyalitas member ke tingkatan Silver / Gold.</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="keranjang.php" class="text-[#ee4d2d] font-bold hover:underline flex items-center gap-1">
                        <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i> Buka Keranjang Belanja →
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Kartu Global TokoKita System (Khusus Kasir & Admin) -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                        <i data-lucide="shield" class="w-4 h-4 text-emerald-600"></i> Likuiditas Dompet Digital
                    </h3>
                    <div class="space-y-2.5">
                        <p class="text-xs text-slate-500">Total Perputaran Saldo Dompet di Seluruh TokoKita POS:</p>
                        <h4 class="text-2xl font-black text-slate-800"><?= rupiah($stat_global['total_dana']) ?></h4>
                        <p class="text-xs text-slate-400">Total <?= $stat_global['total_trx'] ?> kali transaksi deposit member tercatat di sistem audit log.</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="admin_topup.php" class="text-indigo-600 font-bold hover:underline flex items-center gap-1">
                        Modul Kasir Topup →
                    </a>
                    <a href="aktivitas.php" class="text-slate-400 hover:text-slate-600">Audit Log</a>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Form Topup Saldo & Riwayat Mutasi -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Panel Form Top-Up Cepat -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-bold text-base text-slate-800 flex items-center gap-2">
                        <i data-lucide="arrow-down-circle" class="w-5 h-5 text-indigo-600"></i> Form Top-Up Saldo
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Isi saldo dompet instan untuk belanja di TokoKita</p>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Penerima Top-Up Saldo:</label>
                        <?php if (isPelanggan()): ?>
                            <input type="hidden" name="id_pelanggan" value="<?= $id_pel_aktif ?>">
                            <div class="bg-indigo-50 border border-indigo-200 p-2.5 rounded-xl text-xs font-bold text-indigo-950 flex items-center justify-between">
                                <span><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                                <span class="text-[9px] bg-indigo-600 text-white font-extrabold px-2 py-0.5 rounded uppercase">Akun Member Saya</span>
                            </div>
                        <?php else: ?>
                            <select name="id_pelanggan" required class="w-full border border-slate-300 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <?php foreach ($list_pelanggan as $lp): ?>
                                    <option value="<?= $lp['id_pelanggan'] ?>" <?= $lp['id_pelanggan'] == $id_pel_aktif ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lp['nama_pelanggan']) ?> (<?= htmlspecialchars($lp['kota']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Preset Nominal Cepat -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Pilihan Nominal Cepat</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="setNominal(20000)" class="border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 py-2 rounded-xl text-xs font-bold transition">20 Rb</button>
                            <button type="button" onclick="setNominal(50000)" class="border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 py-2 rounded-xl text-xs font-bold transition">50 Rb</button>
                            <button type="button" onclick="setNominal(100000)" class="border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 py-2 rounded-xl text-xs font-bold transition">100 Rb</button>
                            <button type="button" onclick="setNominal(200000)" class="border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 py-2 rounded-xl text-xs font-bold transition">200 Rb</button>
                            <button type="button" onclick="setNominal(300000)" class="border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 py-2 rounded-xl text-xs font-bold transition">300 Rb</button>
                            <button type="button" onclick="setNominal(500000)" class="border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 py-2 rounded-xl text-xs font-bold transition">500 Rb</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Nominal Saldo (Rp)</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                            <input type="number" id="inputNominal" name="nominal" min="10000" step="5000" value="100000" required
                                class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Metode Pembayaran</label>
                        <select name="metode" class="w-full border border-slate-300 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="QRIS Instan (Gopay/OVO/Dana/BCA)">QRIS Instan (Gopay / OVO / Dana / BCA)</option>
                            <option value="DANA Dompet Digital">DANA Dompet Digital</option>
                            <option value="ShopeePay Online">ShopeePay Online</option>
                            <option value="Transfer Bank BCA">Transfer Virtual Account BCA</option>
                            <option value="Transfer Bank BRI">Transfer Virtual Account BRI</option>
                            <option value="Tunai di Kasir Toko">Tunai di Kasir Toko</option>
                        </select>
                    </div>

                    <button type="submit" name="proses_topup" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-indigo-600/30 text-xs transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i> Konfirmasi Pengisian Saldo
                    </button>
                </form>
            </div>

            <!-- Panel Riwayat Mutasi Top-Up -->
            <div class="lg:col-span-2 bg-white p-6 rounded-3xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="font-bold text-base text-slate-800 flex items-center gap-2">
                            <i data-lucide="history" class="w-5 h-5 text-indigo-600"></i> Mutasi & Riwayat Top-Up
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Catatan riwayat saldo masuk terverifikasi</p>
                    </div>

                    <!-- Filter Mode -->
                    <?php if (!isPelanggan()): ?>
                    <div class="flex items-center gap-2">
                        <a href="saldo.php?id_pelanggan=<?= $id_pel_aktif ?>&filter=member" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filter_mode === 'member' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                            Member Ini
                        </a>
                        <a href="saldo.php?id_pelanggan=<?= $id_pel_aktif ?>&filter=all" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filter_mode === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                            Semua Member
                        </a>
                    </div>
                    <?php else: ?>
                    <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1 rounded-xl text-xs font-bold">
                        Riwayat Akun Saya
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Input Pencarian Cepat JS -->
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariRiwayat" onkeyup="filterTable('cariRiwayat', 'tabelRiwayatSaldo')" placeholder="Cari ID transaksi, nama pelanggan, metode bayar..."
                        class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="overflow-x-auto">
                    <table id="tabelRiwayatSaldo" class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[11px] border-b">
                                <th class="p-3">No. Transaksi</th>
                                <th class="p-3">Pelanggan</th>
                                <th class="p-3">Metode</th>
                                <th class="p-3 text-right">Nominal Masuk</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (mysqli_num_rows($q_riwayat) === 0): ?>
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-slate-400">
                                        Belum ada riwayat top-up untuk kategori ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($r = mysqli_fetch_assoc($q_riwayat)): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-3 font-mono font-bold text-indigo-600">#TP-<?= $r['id_topup'] ?></td>
                                    <td class="p-3 font-semibold text-slate-800">
                                        <?= htmlspecialchars($r['nama_pelanggan']) ?>
                                        <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($r['kota']) ?></span>
                                    </td>
                                    <td class="p-3">
                                        <span class="bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg text-[10px] font-bold">
                                            <?= htmlspecialchars($r['metode']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-extrabold text-emerald-600 text-right">
                                        +<?= rupiah($r['nominal']) ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                            <i data-lucide="check" class="w-3 h-3"></i> <?= htmlspecialchars($r['status'] ?? 'Berhasil') ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-slate-400 text-right text-[11px]">
                                        <?= $r['waktu'] ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();
        function setNominal(n) {
            document.getElementById('inputNominal').value = n;
        }
    </script>
</body>
</html>
