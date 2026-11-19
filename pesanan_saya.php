<?php
require_once 'functions.php';
cekLogin();

if (!isPelanggan()) {
    header('Location: index.php');
    exit;
}

$id_pelanggan = getIdPelangganLogin();
$riwayat = null;
$pelanggan = null;
$error_riwayat = '';
$notif_riwayat = '';

if (isset($_SESSION['pesanan_saya_notif'])) {
    $notif_riwayat = (string)$_SESSION['pesanan_saya_notif'];
    unset($_SESSION['pesanan_saya_notif']);
}

if (empty($_SESSION['csrf_pesanan_saya'])) {
    $_SESSION['csrf_pesanan_saya'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batalkan_transaksi'])) {
    $id_batal = filter_var($_POST['id_transaksi'] ?? null, FILTER_VALIDATE_INT);
    $token_dikirim = (string)($_POST['csrf_token'] ?? '');

    if ($id_pelanggan <= 0) {
        $error_riwayat = 'Akun pelanggan belum terhubung dengan data member.';
    } elseif ($id_batal === false || $id_batal <= 0) {
        $error_riwayat = 'Nomor transaksi tidak valid.';
    } elseif (!hash_equals($_SESSION['csrf_pesanan_saya'], $token_dikirim)) {
        $error_riwayat = 'Sesi keamanan tidak valid. Muat ulang halaman, lalu coba lagi.';
    } else {
        mysqli_begin_transaction($conn);
        $q_kunci = mysqli_query($conn, "
            SELECT status_pembayaran
            FROM tbl_transaksi
            WHERE id_transaksi = $id_batal AND id_pelanggan = $id_pelanggan
            LIMIT 1
            FOR UPDATE
        ");
        $transaksi_batal = $q_kunci ? mysqli_fetch_assoc($q_kunci) : null;

        if (!$q_kunci) {
            $error_riwayat = 'Gagal memeriksa transaksi: ' . mysqli_error($conn);
            mysqli_rollback($conn);
        } elseif (!$transaksi_batal) {
            $error_riwayat = 'Transaksi tidak ditemukan pada akun Anda.';
            mysqli_rollback($conn);
        } elseif (!in_array(strtolower(trim((string)$transaksi_batal['status_pembayaran'])), ['pending', 'menunggu pembayaran'], true)) {
            $error_riwayat = 'Transaksi hanya dapat dibatalkan saat masih menunggu pembayaran.';
            mysqli_rollback($conn);
        } else {
            $q_batal = mysqli_query($conn, "
                UPDATE tbl_transaksi
                SET status_pembayaran = 'Dibatalkan'
                WHERE id_transaksi = $id_batal
                  AND id_pelanggan = $id_pelanggan
                  AND LOWER(TRIM(status_pembayaran)) IN ('pending', 'menunggu pembayaran')
            ");
            if (!$q_batal) {
                $error_riwayat = 'Gagal membatalkan transaksi: ' . mysqli_error($conn);
                mysqli_rollback($conn);
            } elseif (mysqli_affected_rows($conn) !== 1) {
                $error_riwayat = 'Status transaksi berubah. Muat ulang halaman sebelum mencoba lagi.';
                mysqli_rollback($conn);
            } else {
                $q_item_batal = mysqli_query($conn, "
                    SELECT id_produk, SUM(jumlah) AS jumlah
                    FROM tbl_detail_transaksi
                    WHERE id_transaksi = $id_batal AND id_produk IS NOT NULL
                    GROUP BY id_produk
                ");
                if (!$q_item_batal) {
                    $error_riwayat = 'Transaksi dibatalkan, tetapi rincian stok gagal dibaca: ' . mysqli_error($conn);
                    mysqli_rollback($conn);
                } else {
                    $stok_berhasil = true;
                    $pesan_gagal_stok = '';
                    while ($item_batal = mysqli_fetch_assoc($q_item_batal)) {
                        $id_produk_batal = (int)$item_batal['id_produk'];
                        $jumlah_kembali = (int)$item_batal['jumlah'];
                        if (!mysqli_query($conn, "UPDATE tbl_produk SET stok = stok + $jumlah_kembali WHERE id_produk = $id_produk_batal")) {
                            $stok_berhasil = false;
                            $pesan_gagal_stok = mysqli_error($conn);
                            break;
                        }
                        if (mysqli_affected_rows($conn) !== 1) {
                            $stok_berhasil = false;
                            $pesan_gagal_stok = "Produk #$id_produk_batal tidak ditemukan.";
                            break;
                        }
                    }

                    if (!$stok_berhasil) {
                        $error_riwayat = 'Pembatalan gagal diselesaikan karena stok tidak dapat dikembalikan: ' . $pesan_gagal_stok;
                        mysqli_rollback($conn);
                    } else {
                        $pesan_log = mysqli_real_escape_string($conn, "Pelanggan membatalkan transaksi #$id_batal; stok dikembalikan.");
                        if (!mysqli_query($conn, "
                            INSERT INTO tbl_log (tabel_terkait, aksi, keterangan)
                            VALUES ('tbl_transaksi', 'BATAL TRANSAKSI PELANGGAN', '$pesan_log')
                        ")) {
                            $error_riwayat = 'Pembatalan gagal dicatat: ' . mysqli_error($conn);
                            mysqli_rollback($conn);
                        } elseif (!mysqli_commit($conn)) {
                            $error_riwayat = 'Gagal menyimpan pembatalan: ' . mysqli_error($conn);
                            mysqli_rollback($conn);
                        } else {
                            $_SESSION['pesanan_saya_notif'] = "Transaksi #TRX-" . str_pad((string)$id_batal, 4, '0', STR_PAD_LEFT) . " berhasil dibatalkan. Stok barang telah dikembalikan.";
                            header('Location: pesanan_saya.php');
                            exit;
                        }
                    }
                }
            }
        }
    }
}

if ($id_pelanggan > 0) {
    $q_pelanggan = mysqli_query($conn, "SELECT nama_pelanggan FROM tbl_pelanggan WHERE id_pelanggan = $id_pelanggan LIMIT 1");
    $pelanggan = $q_pelanggan ? mysqli_fetch_assoc($q_pelanggan) : null;
    if (!$q_pelanggan) {
        $error_riwayat = 'Gagal memuat profil pelanggan: ' . mysqli_error($conn);
    } else {
        $riwayat = mysqli_query($conn, "
            SELECT id_transaksi, tanggal, waktu_transaksi, total_belanja,
                   metode_pembayaran, status_pembayaran, ref_pembayaran
            FROM tbl_transaksi
            WHERE id_pelanggan = $id_pelanggan
            ORDER BY id_transaksi DESC
        ");
        if (!$riwayat) {
            $error_riwayat = 'Gagal memuat riwayat transaksi: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi Saya - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <?php include 'navbar.php'; ?>

    <main class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h1 class="flex items-center gap-2 text-lg font-extrabold">
                    <i data-lucide="history" class="h-5 w-5 text-indigo-600"></i>
                    Riwayat Transaksi Saya
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    <?= htmlspecialchars($pelanggan['nama_pelanggan'] ?? $_SESSION['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?> — hanya transaksi akun Anda.
                </p>
            </div>
            <a href="kategori.php" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">
                Belanja Sekarang
            </a>
        </header>

        <?php if ($error_riwayat !== ''): ?>
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
                <?= htmlspecialchars($error_riwayat, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php elseif ($notif_riwayat !== ''): ?>
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                <?= htmlspecialchars($notif_riwayat, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php elseif ($id_pelanggan <= 0): ?>
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Akun ini belum terhubung ke data pelanggan. Silakan hubungi kasir untuk menghubungkan akun Anda.
            </div>
        <?php elseif ($riwayat && mysqli_num_rows($riwayat) === 0): ?>
            <div role="status" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                Belum ada transaksi pada akun Anda.
            </div>
        <?php elseif ($riwayat): ?>
            <section class="space-y-3" aria-label="Daftar riwayat transaksi">
                <?php while ($transaksi = mysqli_fetch_assoc($riwayat)): ?>
                    <?php
                        $status_tampilan = statusPembayaranTampilan($transaksi['status_pembayaran'] ?? '');
                        $id_transaksi = (int)$transaksi['id_transaksi'];
                        $q_item = mysqli_query($conn, "
                            SELECT nama_produk, jumlah, harga
                            FROM tbl_detail_transaksi
                            WHERE id_transaksi = $id_transaksi
                            ORDER BY id_detail ASC
                        ");
                    ?>
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-sm font-extrabold text-indigo-700">
                                    #TRX-<?= str_pad((string)$id_transaksi, 4, '0', STR_PAD_LEFT) ?>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    <?= htmlspecialchars($transaksi['tanggal'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="mx-1">•</span>
                                    <?= !empty($transaksi['waktu_transaksi']) ? htmlspecialchars(date('H:i:s', strtotime($transaksi['waktu_transaksi'])), ENT_QUOTES, 'UTF-8') : 'Jam belum tercatat' ?>
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold <?= $status_tampilan['class'] ?>">
                                <?= htmlspecialchars($status_tampilan['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-slate-100 pt-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Total transaksi</p>
                                <p class="text-lg font-extrabold text-slate-900"><?= rupiah($transaksi['total_belanja']) ?></p>
                                <?php if (!empty($transaksi['metode_pembayaran'])): ?>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        Metode: <?= htmlspecialchars($transaksi['metode_pembayaran'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($transaksi['ref_pembayaran'])): ?>
                                <p class="text-right text-[11px] text-slate-500">
                                    Referensi<br>
                                    <span class="font-mono font-semibold text-slate-700"><?= htmlspecialchars($transaksi['ref_pembayaran'], ENT_QUOTES, 'UTF-8') ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if (in_array(strtolower(trim((string)($transaksi['status_pembayaran'] ?? ''))), ['pending', 'menunggu pembayaran'], true)): ?>
                            <form method="POST" class="mt-3 border-t border-slate-100 pt-3" onsubmit="return confirm('Batalkan transaksi ini? Stok barang akan dikembalikan dan pesanan tidak dapat dipulihkan.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_pesanan_saya'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id_transaksi" value="<?= $id_transaksi ?>">
                                <button type="submit" name="batalkan_transaksi" value="1" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500">
                                    Batalkan Transaksi
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($q_item && mysqli_num_rows($q_item) > 0): ?>
                            <details class="mt-3 border-t border-slate-100 pt-3">
                                <summary class="cursor-pointer text-xs font-bold text-indigo-700">Lihat rincian barang</summary>
                                <ul class="mt-2 space-y-1.5 text-xs text-slate-600">
                                    <?php while ($item = mysqli_fetch_assoc($q_item)): ?>
                                        <li class="flex justify-between gap-3">
                                            <span><?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?> × <?= (int)$item['jumlah'] ?></span>
                                            <span class="shrink-0 font-semibold"><?= rupiah((float)$item['harga'] * (int)$item['jumlah']) ?></span>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            </details>
                        <?php elseif (!$q_item): ?>
                            <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-rose-700">
                                Rincian barang gagal dimuat: <?= htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
