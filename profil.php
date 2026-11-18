<?php
require_once 'functions.php';
cekLogin();

$notif_sukses = "";
$notif_error  = "";
$nama_aktif   = $_SESSION['nama_lengkap'];
$id_user      = isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : 0;
$user_role    = isset($_SESSION['role']) ? $_SESSION['role'] : 'Kasir POS';

// Ambil info member jika user yang login adalah Pelanggan
$info_member = null;
if (isPelanggan()) {
    $nama_esc = mysqli_real_escape_string($conn, $nama_aktif);
    $q_pel = mysqli_query($conn, "
        SELECT p.*, 
               COALESCE((SELECT SUM(nominal) FROM tbl_topup tp WHERE tp.id_pelanggan = p.id_pelanggan AND tp.status = 'Berhasil'), 0) AS total_saldo,
               (SELECT COUNT(*) FROM tbl_transaksi t WHERE t.id_pelanggan = p.id_pelanggan) AS jml_transaksi,
               (SELECT COALESCE(SUM(total_belanja), 0) FROM tbl_transaksi t WHERE t.id_pelanggan = p.id_pelanggan) AS total_belanja
        FROM tbl_pelanggan p
        WHERE p.nama_pelanggan = '$nama_esc'
        LIMIT 1
    ");
    if ($q_pel && mysqli_num_rows($q_pel) > 0) {
        $info_member = mysqli_fetch_assoc($q_pel);
    }
}

// Update Profil & Password
if (isset($_POST['update_profil'])) {
    $nama_baru = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    
    // RBAC Security: Hanya Administrator yang boleh mengubah Role
    if (isAdmin() && isset($_POST['role'])) {
        $role_baru = mysqli_real_escape_string($conn, trim($_POST['role']));
    } else {
        $role_baru = $user_role;
    }

    $sql_up = "UPDATE tbl_users SET nama_lengkap='$nama_baru', role='$role_baru'";
    if (!empty($_POST['password_baru'])) {
        $pass_hash = password_hash($_POST['password_baru'], PASSWORD_DEFAULT);
        $sql_up .= ", password='$pass_hash'";
    }

    if ($id_user > 0) {
        $sql_up .= " WHERE id_user = $id_user";
    } else {
        $sql_up .= " WHERE nama_lengkap = '" . mysqli_real_escape_string($conn, $nama_aktif) . "'";
    }

    if (mysqli_query($conn, $sql_up)) {
        // Sinkronkan nama dan kota di tbl_pelanggan jika akun Pelanggan
        if (isPelanggan() && $info_member) {
            $kota_baru = isset($_POST['kota']) ? mysqli_real_escape_string($conn, trim($_POST['kota'])) : $info_member['kota'];
            mysqli_query($conn, "UPDATE tbl_pelanggan SET nama_pelanggan='$nama_baru', kota='$kota_baru' WHERE id_pelanggan={$info_member['id_pelanggan']}");
            $info_member['nama_pelanggan'] = $nama_baru;
            $info_member['kota'] = $kota_baru;
        }

        $_SESSION['nama_lengkap'] = $nama_baru;
        $_SESSION['role']         = $role_baru;
        $nama_aktif               = $nama_baru;
        $user_role                = $role_baru;
        $notif_sukses             = "Profil dan pengaturan akun berhasil diperbarui!";
    } else {
        $notif_error = "Gagal memperbarui profil: " . mysqli_error($conn);
    }
}

// Uji Stored Function KonversiNilai(nilai) (Jobsheet 4.E.5)
$skor_uji = isset($_POST['skor_uji']) ? (int)$_POST['skor_uji'] : 92;
$grade = 'A';
$q_nilai = mysqli_query($conn, "SELECT KonversiNilai($skor_uji) AS grade");
if ($q_nilai) {
    $res_nilai = mysqli_fetch_assoc($q_nilai);
    if ($res_nilai && isset($res_nilai['grade'])) {
        $grade = $res_nilai['grade'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">
        <!-- Notifikasi -->
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-bold flex items-center gap-2 shadow-sm">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <span><?= $notif_sukses ?></span>
            </div>
        <?php endif; ?>
        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl text-sm font-bold flex items-center gap-2 shadow-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <!-- Header Profil -->
        <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr <?= isPelanggan() ? 'from-amber-500 to-orange-500 shadow-amber-500/30' : (isKasir() ? 'from-emerald-500 to-teal-600 shadow-emerald-500/30' : 'from-indigo-600 to-violet-600 shadow-indigo-600/30') ?> text-white flex items-center justify-center text-2xl sm:text-3xl font-black shadow-lg">
                    <?= strtoupper(substr($nama_aktif, 0, 1)) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900"><?= htmlspecialchars($nama_aktif) ?></h1>
                        <span class="inline-flex items-center gap-1 <?= isPelanggan() ? 'bg-amber-100 text-amber-800 border-amber-300' : (isKasir() ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-indigo-100 text-indigo-800 border-indigo-300') ?> border text-[11px] font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full <?= isPelanggan() ? 'bg-amber-500' : (isKasir() ? 'bg-emerald-500' : 'bg-indigo-500') ?>"></span>
                            <?= htmlspecialchars($user_role) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        <?= isPelanggan() ? 'Akun Pelanggan TokoKita Official Mall • Pembeli Terdaftar' : (isKasir() ? 'Petugas Kasir Point of Sale (POS 2.0) • Terminal Kasir Aktif' : 'Super Administrator Sistem TokoKita Enterprise') ?>
                    </p>
                </div>
            </div>

            <!-- Tombol Aksi Cepat Sesuai Role -->
            <div class="flex items-center gap-2">
                <?php if (isPelanggan()): ?>
                    <a href="saldo.php" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition active:scale-95">
                        <i data-lucide="wallet" class="w-4 h-4"></i> Isi Saldo
                    </a>
                    <a href="keranjang.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition active:scale-95">
                        <i data-lucide="shopping-cart" class="w-4 h-4"></i> Keranjang
                    </a>
                <?php elseif (isKasir()): ?>
                    <a href="kasir_pos.php" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition active:scale-95">
                        <i data-lucide="scan-barcode" class="w-4 h-4"></i> Scanner POS
                    </a>
                    <a href="admin_produk.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition active:scale-95">
                        <i data-lucide="package" class="w-4 h-4"></i> Produk
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition active:scale-95">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Kolom Kiri: Kartu Status & Info Member / Kasir -->
            <div class="space-y-6">
                <?php if (isPelanggan() && $info_member): ?>
                    <!-- Kartu Info Khusus Pelanggan -->
                    <div class="bg-gradient-to-br from-amber-500 via-orange-500 to-[#ee4d2d] text-white p-6 rounded-3xl shadow-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider bg-white/20 px-2.5 py-1 rounded-full">Member Resmi</span>
                            <i data-lucide="badge-check" class="w-6 h-6 text-white/80"></i>
                        </div>
                        <div>
                            <p class="text-xs text-amber-100 font-semibold">ID Member</p>
                            <h3 class="text-xl font-black">#PEL-<?= str_pad($info_member['id_pelanggan'], 3, '0', STR_PAD_LEFT) ?></h3>
                        </div>
                        <div class="border-t border-white/20 pt-3 flex items-center justify-between">
                            <div>
                                <p class="text-[11px] text-amber-100">Kota Domisili</p>
                                <p class="font-black text-sm"><?= htmlspecialchars($info_member['kota'] ?? '-') ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] text-amber-100">Saldo Dompet</p>
                                <p class="font-black text-sm"><?= rupiah($info_member['total_saldo'] ?? 0) ?></p>
                            </div>
                        </div>
                        <div class="bg-black/15 p-3 rounded-2xl flex items-center justify-between text-xs">
                            <span>Total Belanja:</span>
                            <span class="font-black text-amber-200"><?= rupiah($info_member['total_belanja'] ?? 0) ?></span>
                        </div>
                    </div>
                <?php elseif (isKasir()): ?>
                    <!-- Kartu Info Khusus Kasir -->
                    <div class="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 text-white p-6 rounded-3xl shadow-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider bg-white/20 px-2.5 py-1 rounded-full">Kasir Aktif</span>
                            <i data-lucide="scan-barcode" class="w-6 h-6 text-white/80"></i>
                        </div>
                        <div>
                            <p class="text-xs text-emerald-100 font-semibold">Operator Terminal</p>
                            <h3 class="text-xl font-black"><?= htmlspecialchars($nama_aktif) ?></h3>
                        </div>
                        <div class="border-t border-white/20 pt-3 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-100">Hak Akses:</span>
                                <span class="font-black">Input Barang, Member & Cetak Struk</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-100">Status Hapus Produk:</span>
                                <span class="font-bold text-amber-200">Dibatasi (Admin Only)</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Kartu Info Administrator -->
                    <div class="bg-gradient-to-br from-indigo-700 to-violet-800 text-white p-6 rounded-3xl shadow-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider bg-white/20 px-2.5 py-1 rounded-full">Super Admin</span>
                            <i data-lucide="shield-check" class="w-6 h-6 text-white/80"></i>
                        </div>
                        <div>
                            <p class="text-xs text-indigo-200 font-semibold">Wewenang Sistem</p>
                            <h3 class="text-xl font-black">Full Root Access</h3>
                        </div>
                        <p class="text-xs text-indigo-200 border-t border-white/20 pt-3">
                            Administrator memiliki kontrol penuh atas produk, kategori, pelanggan, transaksi kasir, log trigger, dan laporan.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Jobsheet 4.E.5: Simulasi Function KonversiNilai -->
                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                            <i data-lucide="calculator" class="w-4 h-4"></i>
                        </div>
                        <h3 class="font-extrabold text-xs text-slate-800 uppercase tracking-wider">Jobsheet 4.E.5: Function KonversiNilai</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Uji coba Stored Function MySQL <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600 font-bold">KonversiNilai(nilai)</code> untuk menghitung grade nilai otomatis.
                    </p>
                    <form method="POST" class="bg-slate-50 p-3 rounded-2xl border border-slate-200/60 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-600">Masukkan Nilai Uji (0-100):</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="skor_uji" value="<?= $skor_uji ?>" min="0" max="100" class="w-20 px-3 py-1.5 rounded-xl border border-slate-300 text-slate-900 text-sm font-black text-center focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-1.5 rounded-xl font-bold shadow-sm transition">
                                Cek Grade
                            </button>
                            <div class="ml-auto flex items-center gap-1.5 bg-amber-400 text-slate-950 px-3 py-1 rounded-xl font-black text-sm shadow-sm">
                                <span class="text-[10px] uppercase font-bold text-slate-800">Grade:</span>
                                <span><?= htmlspecialchars($grade) ?></span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Kolom Kanan: Form Edit Profil & Ganti Password -->
            <div class="md:col-span-2">
                <form method="POST" class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-200/80 space-y-5">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="font-black text-base text-slate-900 flex items-center gap-2">
                            <i data-lucide="user-cog" class="w-5 h-5 text-indigo-600"></i>
                            <span>Pengaturan Akun & Keamanan</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">Perbarui nama pengguna, detail alamat/kota, dan kata sandi akun Anda.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($nama_aktif) ?>" required 
                               class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 p-3 rounded-2xl text-sm font-semibold transition">
                    </div>

                    <?php if (isPelanggan()): ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Kota Domisili Member</label>
                            <input type="text" name="kota" value="<?= htmlspecialchars($info_member['kota'] ?? 'Ponorogo') ?>" required 
                                   class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 p-3 rounded-2xl text-sm font-semibold transition">
                            <span class="text-[11px] text-slate-400 mt-1 block">Kota tempat tinggal untuk penyesuaian pesanan dan pengiriman.</span>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Jabatan / Role Akses</label>
                        <?php if (isAdmin()): ?>
                            <input type="text" name="role" value="<?= htmlspecialchars($user_role) ?>" required 
                                   class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 p-3 rounded-2xl text-sm font-semibold transition">
                            <span class="text-[11px] text-slate-400 mt-1 block">Sebagai Administrator, Anda dapat mengedit label peran.</span>
                        <?php else: ?>
                            <div class="bg-slate-50 border border-slate-200 p-3 rounded-2xl flex items-center justify-between">
                                <span class="text-sm font-black text-slate-800"><?= htmlspecialchars($user_role) ?></span>
                                <span class="text-[10px] font-bold text-slate-400 bg-slate-200/70 px-2 py-0.5 rounded-md flex items-center gap-1">
                                    <i data-lucide="lock" class="w-3 h-3"></i> Terkunci (Sesuai Hak Akses)
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400 mt-1 block">Peran akun dikunci oleh sistem untuk menjaga keamanan operasional toko.</span>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Password Baru <span class="text-slate-400 font-normal">(Kosongkan jika tidak ingin mengganti password)</span>
                        </label>
                        <input type="password" name="password_baru" placeholder="Masukkan password baru minimal 6 karakter..." 
                               class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 p-3 rounded-2xl text-sm transition">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="submit" name="update_profil" class="bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-extrabold px-6 py-3 rounded-2xl text-sm transition shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Simpan Perubahan Profil</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
