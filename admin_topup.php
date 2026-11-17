<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif = "";
if (isset($_POST['proses_topup'])) {
    $id_pel  = (int)$_POST['id_pelanggan'];
    $nominal = (float)$_POST['nominal'];
    $metode  = mysqli_real_escape_string($conn, $_POST['metode']);

    mysqli_query($conn, "INSERT INTO tbl_topup (id_pelanggan, nominal, metode) VALUES ($id_pel, $nominal, '$metode')");
    mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_topup', 'TOPUP SALDO', 'Pelanggan ID #$id_pel Topup Rp $nominal via $metode')");
    $notif = "Top-Up saldo sebesar " . rupiah($nominal) . " berhasil diproses!";
}

$q_pel = mysqli_query($conn, "SELECT * FROM view_pelanggan");
$q_riwayat = mysqli_query($conn, "
    SELECT tp.*, p.nama_pelanggan, p.kota
    FROM tbl_topup tp
    JOIN tbl_pelanggan p ON tp.id_pelanggan = p.id_pelanggan
    ORDER BY tp.id_topup DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Topup Saldo - TokoKita</title>
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
                    <i data-lucide="wallet" class="w-5 h-5 text-indigo-600"></i> Input Top-Up Pelanggan
                </h2>
                <div>
                    <label class="text-xs font-bold text-slate-600">Pilih Pelanggan</label>
                    <select name="id_pelanggan" required class="w-full border p-2.5 rounded-xl text-sm mt-1">
                        <?php while ($p = mysqli_fetch_assoc($q_pel)): ?>
                            <option value="<?= $p['id_pelanggan'] ?>"><?= $p['nama_pelanggan'] ?> (<?= $p['kota'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600">Nominal Top-Up (Rp)</label>
                    <input type="number" name="nominal" value="100000" min="10000" required class="w-full border p-2.5 rounded-xl text-sm mt-1">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600">Metode Pembayaran</label>
                    <select name="metode" class="w-full border p-2.5 rounded-xl text-sm mt-1">
                        <option value="QRIS">QRIS</option>
                        <option value="DANA">DANA</option>
                        <option value="ShopeePay">ShopeePay</option>
                        <option value="Tunai Kasir">Tunai Kasir</option>
                    </select>
                </div>
                <button type="submit" name="proses_topup" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl text-sm">
                    Proses Top-Up Sekarang
                </button>
            </form>

            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="font-bold text-base mb-4">Riwayat Top-Up Member (`tbl_topup`)</h2>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-left text-xs text-slate-600">
                            <th class="p-3">ID</th>
                            <th class="p-3">Pelanggan</th>
                            <th class="p-3">Metode</th>
                            <th class="p-3">Nominal</th>
                            <th class="p-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = mysqli_fetch_assoc($q_riwayat)): ?>
                        <tr class="border-b">
                            <td class="p-3 font-mono text-xs">#TP-<?= $r['id_topup'] ?></td>
                            <td class="p-3 font-semibold"><?= $r['nama_pelanggan'] ?> (<?= $r['kota'] ?>)</td>
                            <td class="p-3"><span class="bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full text-xs font-bold"><?= $r['metode'] ?></span></td>
                            <td class="p-3 font-bold text-emerald-600"><?= rupiah($r['nominal']) ?></td>
                            <td class="p-3 text-xs text-slate-400"><?= $r['waktu'] ?></td>
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