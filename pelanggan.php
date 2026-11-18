<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif_sukses = "";
$notif_error  = "";

// 1. Tambah Pelanggan via Stored Procedure TambahPelanggan (Bab 3)
if (isset($_POST['tambah_pelanggan'])) {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_pelanggan']));
    $kota = mysqli_real_escape_string($conn, trim($_POST['kota']));
    if (mysqli_query($conn, "CALL TambahPelanggan('$nama', '$kota')")) {
        $notif_sukses = "Pelanggan '$nama' berhasil ditambahkan (Trigger after_insert_pelanggan tercatat)!";
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
    } else {
        $notif_error = "Gagal: " . mysqli_error($conn);
    }
}

// 2. Edit Pelanggan (Memicu Trigger after_update_pelanggan di Bab 5)
if (isset($_POST['update_pelanggan'])) {
    $id   = (int)$_POST['id_pelanggan'];
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_pelanggan']));
    $kota = mysqli_real_escape_string($conn, trim($_POST['kota']));
    if (mysqli_query($conn, "UPDATE tbl_pelanggan SET nama_pelanggan='$nama', kota='$kota' WHERE id_pelanggan=$id")) {
        $notif_sukses = "Data pelanggan #$id diperbarui (Trigger after_update_pelanggan mencatat nama lama & baru)!";
    }
}

// 3. Hapus Pelanggan (Khusus Hak Akses Administrator)
if (isset($_GET['hapus'])) {
    if (!isAdmin()) {
        $notif_error = "Akses Ditolak: Kasir tidak diizinkan menghapus data pelanggan! Fitur hapus pelanggan hanya untuk Administrator.";
    } else {
        $id = (int)$_GET['hapus'];
        if (mysqli_query($conn, "DELETE FROM tbl_pelanggan WHERE id_pelanggan=$id")) {
            $notif_sukses = "Pelanggan #$id berhasil dihapus oleh Administrator (Trigger after_delete_pelanggan tercatat)!";
        } else {
            $notif_error = "Pelanggan tidak dapat dihapus karena sudah memiliki riwayat transaksi.";
        }
    }
}

// Memanggil Stored Procedure HitungPelanggan(OUT jumlah) (Bab 3.D)
mysqli_query($conn, "CALL HitungPelanggan(@total_pel)");
$res_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT @total_pel AS total"));

// Ambil data pelanggan dari View + Stored Function KategoriBelanja
$q_pelanggan = mysqli_query($conn, "
    SELECT v.*, p.kota, KategoriBelanja(v.total_belanja) AS kategori
    FROM view_total_belanja v
    JOIN tbl_pelanggan p ON v.id_pelanggan = p.id_pelanggan
    ORDER BY v.id_pelanggan DESC
");

$q_log_ubah = mysqli_query($conn, "SELECT * FROM log_perubahan_pelanggan ORDER BY id_log DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Pelanggan - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-medium"><?= $notif_sukses ?></div>
        <?php endif; ?>
        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl text-sm font-medium"><?= $notif_error ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Tambah & Log Perubahan Trigger -->
            <div class="space-y-6">
                <form method="POST" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h2 class="font-bold text-base flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-5 h-5 text-indigo-600"></i> Tambah Pelanggan
                        </h2>
                        <span class="text-xs bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full font-bold">Total: <?= $res_total['total'] ?> Orang</span>
                    </div>
                    <input type="text" name="nama_pelanggan" placeholder="Nama Pelanggan" required class="w-full border p-2.5 rounded-xl text-sm">
                    <input type="text" name="kota" placeholder="Kota Domisili (mis: Ponorogo)" required class="w-full border p-2.5 rounded-xl text-sm">
                    <button type="submit" name="tambah_pelanggan" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-xl transition">
                        Simpan (`CALL TambahPelanggan`)
                    </button>
                </form>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h3 class="font-bold text-sm mb-3 flex items-center gap-2 text-amber-700">
                        <i data-lucide="history" class="w-4 h-4"></i> Riwayat Edit Nama (`Trigger after_update_pelanggan`)
                    </h3>
                    <table class="w-full text-xs border-collapse">
                        <thead><tr class="bg-slate-100"><th class="p-2 text-left">Nama Lama (OLD)</th><th class="p-2 text-left">Nama Baru (NEW)</th><th class="p-2 text-right">Waktu</th></tr></thead>
                        <tbody>
                            <?php while ($lu = mysqli_fetch_assoc($q_log_ubah)): ?>
                            <tr class="border-b">
                                <td class="p-2 text-rose-600 line-through"><?= $lu['nama_lama'] ?></td>
                                <td class="p-2 text-emerald-700 font-bold"><?= $lu['nama_baru'] ?></td>
                                <td class="p-2 text-right text-slate-400"><?= $lu['waktu'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Daftar Pelanggan & Edit Cepat -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="font-bold text-base mb-4">Daftar Pelanggan & Status Member (`view_total_belanja`)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-left text-xs text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3">Edit Nama & Kota (`Trigger UPDATE`)</th>
                                <th class="p-3 text-center">Transaksi</th>
                                <th class="p-3">Total Belanja</th>
                                <th class="p-3">Level</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = mysqli_fetch_assoc($q_pelanggan)): ?>
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-2.5 font-mono text-xs">#<?= $p['id_pelanggan'] ?></td>
                                <td class="p-2.5">
                                    <form method="POST" class="flex items-center gap-1.5">
                                        <input type="hidden" name="id_pelanggan" value="<?= $p['id_pelanggan'] ?>">
                                        <input type="text" name="nama_pelanggan" value="<?= htmlspecialchars($p['nama_pelanggan']) ?>" class="border rounded-lg px-2 py-1 text-xs w-32 font-semibold">
                                        <input type="text" name="kota" value="<?= htmlspecialchars($p['kota']) ?>" class="border rounded-lg px-2 py-1 text-xs w-24">
                                        <button type="submit" name="update_pelanggan" class="bg-amber-500 hover:bg-amber-600 text-white text-xs px-2.5 py-1 rounded-lg font-semibold">Update</button>
                                    </form>
                                </td>
                                <td class="p-2.5 text-center"><?= $p['total_transaksi'] ?>x</td>
                                <td class="p-2.5 font-semibold">Rp <?= number_format($p['total_belanja'], 0, ',', '.') ?></td>
                                <td class="p-2.5">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $p['kategori'] == 'Pelanggan Gold' ? 'bg-amber-100 text-amber-800' : ($p['kategori'] == 'Pelanggan Silver' ? 'bg-slate-200 text-slate-800' : 'bg-blue-100 text-blue-700') ?>">
                                        <?= $p['kategori'] ?>
                                    </span>
                                </td>
                                <td class="p-2.5 text-right whitespace-nowrap">
                                    <a href="pesanan_saya.php?id_pelanggan=<?= $p['id_pelanggan'] ?>" class="text-indigo-600 hover:underline text-xs font-semibold mr-2">Pesanan</a>
                                    <?php if (isAdmin()): ?>
                                        <a href="?hapus=<?= $p['id_pelanggan'] ?>" onclick="return confirm('Hapus pelanggan <?= htmlspecialchars($p['nama_pelanggan']) ?>?')" class="text-rose-600 hover:underline text-xs font-semibold">Hapus</a>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs italic">Kasir: No Delete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>