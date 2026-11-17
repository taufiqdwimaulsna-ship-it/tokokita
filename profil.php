<?php
session_start();
if (!isset($_SESSION['login_toko'])) { header("Location: login.php"); exit; }
require_once 'koneksi.php';

$notif_sukses = "";
$nama_aktif   = $_SESSION['nama_lengkap'];

// Update Profil & Password
if (isset($_POST['update_profil'])) {
    $nama_baru = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $role_baru = mysqli_real_escape_string($conn, trim($_POST['role']));

    $sql_up = "UPDATE tbl_users SET nama_lengkap='$nama_baru', role='$role_baru'";
    if (!empty($_POST['password_baru'])) {
        $pass_hash = password_hash($_POST['password_baru'], PASSWORD_DEFAULT);
        $sql_up .= ", password='$pass_hash'";
    }
    $sql_up .= " WHERE nama_lengkap = '" . mysqli_real_escape_string($conn, $nama_aktif) . "'";

    if (mysqli_query($conn, $sql_up)) {
        $_SESSION['nama_lengkap'] = $nama_baru;
        $_SESSION['role']         = $role_baru;
        $notif_sukses = "Profil dan pengaturan keamanan berhasil diperbarui!";
    }
}

// Uji Stored Function KonversiNilai(nilai) (Jobsheet 4.E.5)
$skor_uji = isset($_POST['skor_uji']) ? (int)$_POST['skor_uji'] : 92;
$res_nilai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT KonversiNilai($skor_uji) AS grade"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Saya - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-5xl mx-auto p-6 space-y-6">
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-medium"><?= $notif_sukses ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Kartu Profil Pengguna -->
            <div class="bg-gradient-to-br from-indigo-700 to-violet-800 text-white p-6 rounded-2xl shadow-lg flex flex-col justify-between">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-white/20 mx-auto flex items-center justify-center text-3xl font-extrabold mb-3 border-2 border-white/40">
                        <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                    </div>
                    <h2 class="text-xl font-bold"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h2>
                    <span class="inline-block bg-emerald-400/20 text-emerald-200 border border-emerald-400/30 px-3 py-0.5 rounded-full text-xs font-semibold mt-1">
                        <?= htmlspecialchars($_SESSION['role']) ?>
                    </span>
                </div>

                <!-- Simulasi Function KonversiNilai -->
                <form method="POST" class="mt-6 bg-black/20 p-4 rounded-xl space-y-2">
                    <p class="text-xs font-bold text-indigo-200">Simulasi `Function KonversiNilai(nilai)`</p>
                    <div class="flex items-center gap-2">
                        <input type="number" name="skor_uji" value="<?= $skor_uji ?>" min="0" max="100" class="w-20 px-2 py-1 rounded text-slate-900 text-sm font-bold text-center">
                        <button type="submit" class="bg-indigo-500 hover:bg-indigo-400 text-white text-xs px-3 py-1.5 rounded-lg font-semibold">Cek Grade</button>
                        <span class="ml-auto text-lg font-extrabold bg-amber-400 text-slate-900 px-3 py-0.5 rounded-lg">
                            Grade: <?= $res_nilai['grade'] ?>
                        </span>
                    </div>
                </form>
            </div>

            <!-- Form Edit Profil & Ganti Password -->
            <form method="POST" class="md:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <h2 class="font-bold text-base flex items-center gap-2 border-b pb-3">
                    <i data-lucide="user-cog" class="w-5 h-5 text-indigo-600"></i> Pengaturan Profil & Keamanan Akun
                </h2>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($_SESSION['nama_lengkap']) ?>" required class="w-full border p-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Jabatan / Role</label>
                    <input type="text" name="role" value="<?= htmlspecialchars($_SESSION['role']) ?>" required class="w-full border p-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Password Baru (Kosongkan jika tidak ingin mengganti)</label>
                    <input type="password" name="password_baru" placeholder="Masukkan password baru..." class="w-full border p-2.5 rounded-xl text-sm">
                </div>
                <button type="submit" name="update_profil" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition">
                    Simpan Perubahan Profil
                </button>
            </form>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>