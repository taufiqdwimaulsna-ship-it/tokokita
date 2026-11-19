<?php
/**
 * TokoKita - Registrasi Pengguna & Pelanggan Baru
 * File: register.php
 */
require_once 'functions.php';

$sukses = "";
$error  = "";

if (isset($_POST['btn_register'])) {
    $nama      = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $username  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $kota      = mysqli_real_escape_string($conn, trim($_POST['kota']));
    $role      = isset($_POST['role']) ? mysqli_real_escape_string($conn, $_POST['role']) : 'Pelanggan';
    $password  = $_POST['password'];
    $konfirm   = $_POST['konfirmasi_password'];

    // Validasi
    if (strlen($username) < 3) {
        $error = "Username minimal harus 3 karakter!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal harus 6 karakter!";
    } elseif ($password !== $konfirm) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Cek apakah username sudah ada
        $cek_u = mysqli_query($conn, "SELECT id_user FROM tbl_users WHERE username = '$username'");
        if (mysqli_num_rows($cek_u) > 0) {
            $error = "Username '$username' sudah terdaftar! Silakan gunakan username lain.";
        } else {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);

            // Simpan ke tbl_users
            $q_user = mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) VALUES ('$username', '$pass_hash', '$nama', '$role')");

            if ($q_user) {
                // Jika mendaftar sebagai Pelanggan, daftarkan juga ke tbl_pelanggan via Stored Procedure TambahPelanggan
                if ($role === 'Pelanggan') {
                    $kota_pel = !empty($kota) ? $kota : 'Ponorogo';
                    $id_user_baru = (int)mysqli_insert_id($conn);
                    if (mysqli_query($conn, "INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES ('$nama', '$kota_pel')")) {
                        $id_new_pel = (int)mysqli_insert_id($conn);
                        $link_user = mysqli_query($conn, "UPDATE tbl_users SET id_pelanggan = $id_new_pel WHERE id_user = $id_user_baru");
                        $bonus_saldo = mysqli_query($conn, "INSERT INTO tbl_topup (id_pelanggan, nominal, metode, status) VALUES ($id_new_pel, 50000, 'Bonus Registrasi', 'Berhasil')");
                        if (!$link_user || !$bonus_saldo) {
                            $error = "Akun berhasil dibuat, tetapi data pelanggan belum selesai disiapkan: " . mysqli_error($conn);
                        }
                    } else {
                        $error = "Akun berhasil dibuat, tetapi data pelanggan gagal disimpan: " . mysqli_error($conn);
                    }
                }

                // Catat log aktivitas sistem
                if ($error === '') {
                    mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_users', 'REGISTRASI', 'Pendaftaran akun baru username: $username ($role)')");
                    $sukses = "Pendaftaran berhasil! Akun '$username' telah aktif. Silakan masuk untuk mulai berbelanja.";
                }
            } else {
                $error = "Gagal mendaftarkan akun: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru - TokoKita</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-4 md:p-6 font-sans">

    <div class="max-w-4xl w-full bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-5">
        
        <!-- Panel Samping: Informasi Promo Registrasi -->
        <div class="md:col-span-2 bg-gradient-to-br from-indigo-600 to-violet-700 p-8 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="relative z-10 space-y-4">
                <div class="inline-flex items-center gap-2 bg-white/20 px-3 py-1.5 rounded-full text-xs font-semibold backdrop-blur-sm">
                    <i data-lucide="sparkles" class="w-4 h-4 text-amber-300"></i> Member Baru TokoKita
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold leading-tight">Bergabung Bersama Kami</h1>
                <p class="text-indigo-100 text-xs leading-relaxed">
                    Dapatkan saldo dompet awal, nikmati voucher diskon belanja, kemudahan transaksi kasir POS, dan pelacakan pesanan secara real-time.
                </p>

                <div class="space-y-2.5 pt-2">
                    <div class="flex items-center gap-2.5 text-xs bg-white/10 p-2.5 rounded-xl backdrop-blur-sm">
                        <i data-lucide="wallet" class="w-4 h-4 text-emerald-300 shrink-0"></i>
                        <span>Bonus Saldo Dompet Awal Rp 50.000</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-xs bg-white/10 p-2.5 rounded-xl backdrop-blur-sm">
                        <i data-lucide="percent" class="w-4 h-4 text-amber-300 shrink-0"></i>
                        <span>Kupon diskon hingga 25% setiap minggu</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-xs bg-white/10 p-2.5 rounded-xl backdrop-blur-sm">
                        <i data-lucide="shield-check" class="w-4 h-4 text-cyan-300 shrink-0"></i>
                        <span>Audit database terjamin & transaksi aman</span>
                    </div>
                </div>
            </div>

            <div class="relative z-10 pt-6 border-t border-white/20 mt-6 text-xs text-indigo-200">
                Sudah punya akun?
                <a href="login.php" class="text-white font-bold underline hover:text-amber-200 ml-1">Masuk Sekarang →</a>
            </div>
        </div>

        <!-- Panel Form Registrasi -->
        <div class="md:col-span-3 bg-white p-8 md:p-10 flex flex-col justify-center">
            <div class="mb-5">
                <h2 class="text-2xl font-bold text-slate-800">Buat Akun Anda</h2>
                <p class="text-xs text-slate-500 mt-1">Lengkapi data diri untuk membuat akun TokoKita</p>
            </div>

            <?php if ($sukses): ?>
                <div class="mb-5 bg-emerald-50 border border-emerald-300 text-emerald-800 p-4 rounded-2xl text-xs font-semibold space-y-2">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
                        <span><?= $sukses ?></span>
                    </div>
                    <div class="pt-1">
                        <a href="login.php" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl font-bold transition">
                            <i data-lucide="log-in" class="w-4 h-4"></i> Masuk Sekarang ke Akun Anda
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-5 bg-rose-50 border border-rose-300 text-rose-700 p-3 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required placeholder="Contoh: Budi Santoso"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Kota Domisili</label>
                        <input type="text" name="kota" required placeholder="Contoh: Ponorogo / Madiun"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Username Login</label>
                        <input type="text" name="username" required placeholder="Huruf kecil / angka"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Peran Akun</label>
                        <select name="role" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                            <option value="Pelanggan" selected>Pelanggan (Member Toko)</option>
                            <option value="Kasir POS">Kasir POS</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Password</label>
                        <div class="relative">
                            <input type="password" id="pass1" name="password" required placeholder="Min 6 karakter"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition pr-10">
                            <button type="button" onclick="togglePass('pass1')" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Konfirmasi Password</label>
                        <div class="relative">
                            <input type="password" id="pass2" name="konfirmasi_password" required placeholder="Ulangi password"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition pr-10">
                            <button type="button" onclick="togglePass('pass2')" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" name="btn_register"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Selesaikan Pendaftaran
                    </button>
                </div>
            </form>

            <div class="mt-5 text-center text-xs text-slate-400 border-t pt-3">
                Dengan mendaftar, Anda menyetujui Ketentuan Layanan & Kebijakan Privasi TokoKita.
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();
        function togglePass(id) {
            const el = document.getElementById(id);
            if (el) {
                el.type = el.type === 'password' ? 'text' : 'password';
            }
        }
    </script>
</body>
</html>
