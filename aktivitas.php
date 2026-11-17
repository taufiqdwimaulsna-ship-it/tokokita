<?php
require_once 'functions.php';
batasiAksesPelanggan();

$q_log_pel  = mysqli_query($conn, "SELECT * FROM log_pelanggan ORDER BY id_log DESC LIMIT 20");
$q_log_ubah = mysqli_query($conn, "SELECT * FROM log_perubahan_pelanggan ORDER BY id_log DESC LIMIT 20");
$q_log_umum = mysqli_query($conn, "SELECT * FROM tbl_log ORDER BY id_log DESC LIMIT 20");

$c1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM log_pelanggan"))['c'];
$c2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM log_perubahan_pelanggan"))['c'];
$c3 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_log"))['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas Trigger - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Header -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                    <i data-lucide="activity" class="w-5 h-5 text-indigo-600"></i> Pemantauan Audit & Trigger Database
                </h1>
                <p class="text-xs text-slate-400 mt-0.5">Rekaman realtime dari Trigger MariaDB: <code>AFTER INSERT</code>, <code>AFTER UPDATE</code>, dan <code>AFTER DELETE</code></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-xl text-xs font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Audit Engine Active
                </span>
            </div>
        </div>

        <!-- 3 Kolom Log Trigger -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- 1. log_pelanggan -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="font-bold text-xs uppercase tracking-wider text-indigo-700 flex items-center gap-1.5">
                            <i data-lucide="user-check" class="w-4 h-4"></i> Log Pelanggan
                        </h2>
                        <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full text-[10px] font-extrabold"><?= $c1 ?> Event</span>
                    </div>

                    <div class="overflow-x-auto max-h-[460px] overflow-y-auto pr-1">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase border-b">
                                    <th class="p-2">Aksi</th>
                                    <th class="p-2">ID Pel</th>
                                    <th class="p-2 text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php while ($l1 = mysqli_fetch_assoc($q_log_pel)): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-2 font-mono font-bold text-indigo-600 text-[11px]"><?= $l1['aktivitas'] ?></td>
                                    <td class="p-2 font-bold text-slate-700">#<?= $l1['id_pelanggan'] ?></td>
                                    <td class="p-2 text-right text-slate-400 text-[10px]"><?= date('H:i:s d/m', strtotime($l1['waktu'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 border-t pt-2">Trigger: <code>after_insert_pelanggan</code> & <code>after_delete_pelanggan</code></p>
            </div>

            <!-- 2. log_perubahan_pelanggan -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="font-bold text-xs uppercase tracking-wider text-amber-700 flex items-center gap-1.5">
                            <i data-lucide="git-commit" class="w-4 h-4"></i> Log Perubahan Nama
                        </h2>
                        <span class="bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full text-[10px] font-extrabold"><?= $c2 ?> Event</span>
                    </div>

                    <div class="overflow-x-auto max-h-[460px] overflow-y-auto pr-1">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase border-b">
                                    <th class="p-2">Nama Lama</th>
                                    <th class="p-2">Nama Baru</th>
                                    <th class="p-2 text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php while ($l2 = mysqli_fetch_assoc($q_log_ubah)): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-2 text-rose-500 line-through text-[11px]"><?= htmlspecialchars($l2['nama_lama']) ?></td>
                                    <td class="p-2 font-bold text-emerald-700 text-[11px]"><?= htmlspecialchars($l2['nama_baru']) ?></td>
                                    <td class="p-2 text-right text-slate-400 text-[10px]"><?= date('H:i:s d/m', strtotime($l2['waktu'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 border-t pt-2">Trigger: <code>after_update_pelanggan</code> (OLD vs NEW)</p>
            </div>

            <!-- 3. tbl_log -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="font-bold text-xs uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                            <i data-lucide="database" class="w-4 h-4"></i> Log Stok & Mutasi
                        </h2>
                        <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full text-[10px] font-extrabold"><?= $c3 ?> Event</span>
                    </div>

                    <div class="overflow-x-auto max-h-[460px] overflow-y-auto pr-1">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase border-b">
                                    <th class="p-2">Aksi</th>
                                    <th class="p-2">Rincian</th>
                                    <th class="p-2 text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php while ($l3 = mysqli_fetch_assoc($q_log_umum)): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-2 font-mono font-bold text-indigo-600 text-[11px]"><?= $l3['aksi'] ?></td>
                                    <td class="p-2 text-slate-700 text-[11px]"><?= htmlspecialchars($l3['keterangan']) ?></td>
                                    <td class="p-2 text-right text-slate-400 text-[10px]"><?= date('H:i:s d/m', strtotime($l3['waktu'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 border-t pt-2">Trigger: <code>trg_kurangi_stok_dan_audit</code></p>
            </div>

        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
