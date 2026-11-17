<?php
require_once 'functions.php';
cekLogin();

$notif = "";
if (isset($_POST['tambah_voucher'])) {
    $kode   = strtoupper(mysqli_real_escape_string($conn, trim($_POST['kode_voucher'])));
    $persen = (float)$_POST['persen_diskon'];
    $min    = (float)$_POST['min_belanja'];
    mysqli_query($conn, "INSERT IGNORE INTO tbl_voucher (kode_voucher, persen_diskon, min_belanja) VALUES ('$kode', $persen, $min)");
    $notif = "Voucher '$kode' ($persen%) berhasil ditambahkan!";
}

$persen_simulasi = isset($_GET['simulasi']) ? (float)$_GET['simulasi'] : 15;
$q_voucher = mysqli_query($conn, "SELECT * FROM tbl_voucher ORDER BY persen_diskon ASC");
$q_simulasi = mysqli_query($conn, "
    SELECT nama_produk, harga,
           HitungDiskon(harga, $persen_simulasi) AS potongan,
           (harga - HitungDiskon(harga, $persen_simulasi)) AS harga_promo
    FROM tbl_produk
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Promo & Diskon - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <?php if ($notif): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-medium"><?= $notif ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <h2 class="font-bold text-base flex items-center gap-2 border-b pb-3">
                    <i data-lucide="ticket" class="w-5 h-5 text-indigo-600"></i> Buat Voucher Diskon
                </h2>
                <input type="text" name="kode_voucher" placeholder="Kode (mis: MERDEKA17)" required class="w-full border p-2.5 rounded-xl text-sm uppercase">
                <input type="number" name="persen_diskon" placeholder="Besar Diskon (%)" min="1" max="90" required class="w-full border p-2.5 rounded-xl text-sm">
                <input type="number" name="min_belanja" placeholder="Minimal Belanja (Rp)" required class="w-full border p-2.5 rounded-xl text-sm">
                <button type="submit" name="tambah_voucher" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl text-sm">Simpan Voucher</button>

                <div class="pt-4 border-t space-y-2">
                    <p class="text-xs font-bold text-slate-500">Voucher Aktif:</p>
                    <?php while ($v = mysqli_fetch_assoc($q_voucher)): ?>
                    <div class="flex justify-between items-center bg-amber-50 border border-amber-200 px-3 py-2 rounded-xl text-xs">
                        <span class="font-mono font-bold text-amber-900"><?= $v['kode_voucher'] ?></span>
                        <span class="font-bold text-emerald-700">Diskon <?= (int)$v['persen_diskon'] ?>%</span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </form>

            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                    <h2 class="font-bold text-base">Simulasi Stored Function `HitungDiskon(harga, <?= $persen_simulasi ?>)`</h2>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="number" name="simulasi" value="<?= $persen_simulasi ?>" min="1" max="90" class="w-20 border p-1.5 rounded-lg text-xs text-center font-bold">
                        <button type="submit" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Uji %</button>
                    </form>
                </div>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-left text-xs text-slate-600">
                            <th class="p-3">Nama Produk</th>
                            <th class="p-3">Harga Normal</th>
                            <th class="p-3">Potongan (`HitungDiskon`)</th>
                            <th class="p-3">Harga Setelah Diskon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = mysqli_fetch_assoc($q_simulasi)): ?>
                        <tr class="border-b">
                            <td class="p-3 font-semibold"><?= $s['nama_produk'] ?></td>
                            <td class="p-3 text-slate-400 line-through"><?= rupiah($s['harga']) ?></td>
                            <td class="p-3 text-rose-600 font-semibold">-<?= rupiah($s['potongan']) ?></td>
                            <td class="p-3 font-extrabold text-emerald-700"><?= rupiah($s['harga_promo']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>