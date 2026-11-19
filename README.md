# 🛒 TokoKita POS & Inventory Management System

**TokoKita** adalah Sistem Informasi Penjualan (Point of Sale), Manajemen Inventori, dan Dompet Digital Terintegrasi berbasis web yang dirancang menggunakan arsitektur **SQL Lanjutan (Advanced MariaDB / MySQL)** dan **PHP Native**.

Sistem ini mengimplementasikan konsep basis data enterprise, mencakup **Database Views**, **Stored Procedures**, **Stored Functions**, **Database Triggers (Audit & Stock Automation)**, serta **ACID Database Transactions (`COMMIT` & `ROLLBACK`)**.

---

## 🚀 Fitur Utama Sistem

### 1. 💳 Kasir POS & Checkout Transaksi
- **Kasir Express 1-Klik**: Menjalankan transaksi cepat langsung memanggil Stored Procedure `sp_TambahTransaksiBaru`.
- **Metode Pembayaran**: Kanal Duitku aktif dimuat otomatis untuk QRIS, Virtual Account, gerai, dan kartu kredit; e-wallet/PayLater disembunyikan dari checkout. Tunai dan Saldo TokoKita tetap tersedia.
- **Riwayat Transaksi Pelanggan**: Pelanggan dapat melihat transaksi milik akunnya sendiri, status pembayaran, waktu transaksi, serta rincian barang.
- **Multi-Keranjang Belanja**: Belanja banyak item sekaligus dengan kalkulasi diskon voucher dan ringkasan subtotal.
- **Struk Kasir Thermal (`struk.php`)**: Desain struk thermal profesional siap cetak (`@media print` 80mm/58mm), simulasi barcode, dan rincian PPN 11% otomatis.

### 2. 👛 Dompet Digital & Manajemen Saldo (`saldo.php`)
- **TokoKita Pay Member**: Kartu digital member virtual dengan status level otomatis (`Pelanggan Gold`, `Pelanggan Silver`, `Pelanggan Reguler`).
- **Multi-Payment Top-Up**: Pengisian saldo instan via QRIS, Virtual Account Bank (BCA, BRI), E-Wallet (DANA, ShopeePay, OVO), dan Tunai Kasir.
- **Mutasi & Riwayat Real-Time**: Pencarian cepat dan penyaringan mutasi saldo masuk dan keluar.

### 3. 🔐 Autentikasi & Registrasi Multi-Role (`login.php` & `register.php`)
- **Tiga Peran Pengguna (Role)**:
  - `Super Admin`: Akses menyeluruh ke seluruh modul manajemen, laporan, dan konfigurasi.
  - `Kasir POS`: Mengelola antrean kasir, proses transaksi, top-up pelanggan, dan cetak struk.
  - `Pelanggan`: Memantau saldo, katalog produk, wishlist favorit, dan histori pesanan.
- **Enkripsi Sandi Modern**: Menggunakan algoritma `password_hash()` BCRYPT.
- **Bonus Registrasi**: Member baru otomatis terdaftar di `tbl_pelanggan` dan mendapatkan bonus saldo awal.

### 4. 📦 Inventori & Manajemen Katalog Produk
- Manajemen Kategori (`tbl_kategori`) dan Produk (`tbl_produk`).
- Foto produk dapat diunggah saat membuat SKU; bila kosong, katalog otomatis memilih gambar berdasarkan nama produk.
- Monitoring stok aman & penambahan stok barang.
- Kalkulasi PPN 11% dan estimasi diskon otomatis menggunakan database function.

### 5. ⚡ Otomatisasi & Audit Log Database Trigger (`aktivitas.php`)
- **Pemotongan Stok Otomatis**: Trigger `AFTER INSERT` pada `tbl_detail_transaksi` otomatis memotong stok barang di gudang.
- **Pencatatan Audit Pelanggan**: Trigger mencatat histori penambahan, perubahan (`OLD` vs `NEW`), serta penghapusan data pelanggan.
- **Validasi Nilai Belanja Negatif**: Trigger `BEFORE INSERT` menolak transaksi dengan nilai minus.

### 6. 📊 Laporan Eksekutif & Analisis Query SQL (`laporan.php`)
- Multiple JOIN 3 tabel transaksi.
- Subquery analisa pelanggan belum pernah bertransaksi.
- Transaksi teratas dengan nilai belanja terbesar.
- Penggabungan data cabang dan pelanggan dengan klausa `UNION`.
- Rencana eksekusi query menggunakan `EXPLAIN`.

---

## 🛠️ Arsitektur SQL Lanjutan (MariaDB / MySQL)

### A. Tabel Utama (`db_tokokita`)
| Nama Tabel | Deskripsi |
|---|---|
| `tbl_users` | Akun pengguna sistem (Admin, Kasir, Pelanggan) |
| `tbl_pelanggan` | Data profil dan kota domisili pelanggan |
| `tbl_kategori` | Klasifikasi kategori barang |
| `tbl_produk` | Master katalog barang, harga, dan stok |
| `tbl_transaksi` | Header transaksi belanja & tanggal |
| `tbl_detail_transaksi` | Rincian item barang, harga, dan kuantitas per transaksi |
| `tbl_topup` | Mutasi pengisian saldo dompet member |
| `tbl_voucher` | Kupon diskon dan batas minimal belanja |
| `tbl_favorit` | Daftar produk favorit / wishlist pengguna |
| `tbl_cabang` | Data lokasi outlet dan cabang toko |
| `tbl_log` | Log transaksi dan aktivitas audit sistem |
| `log_pelanggan` | Log trigger penambahan dan penghapusan member |
| `log_perubahan_pelanggan` | Log trigger perubahan nama pelanggan (`OLD` vs `NEW`) |

---

### B. Database Views
- **`view_pelanggan`**: Menampilkan daftar ringkas pelanggan (`id_pelanggan`, `nama_pelanggan`, `kota`).
- **`view_total_belanja`**: Rekapitulasi jumlah frekuensi transaksi dan total belanja kumulatif per pelanggan.
- **`view_transaksi_pelanggan`**: Penggabungan riwayat transaksi lengkap dengan data nama dan kota pelanggan.

---

### C. Stored Functions
1. **`HitungTotal(harga, jumlah)`**: Menghitung subtotal barang (`harga * jumlah`).
2. **`HitungDiskon(harga, diskon)`**: Menghitung besaran nilai potongan diskon dari persentase.
3. **`HitungPajak(harga)`**: Menghitung besaran PPN 11% dari total belanja.
4. **`KategoriBelanja(total)`**: Menentukan kategori loyalitas (`Gold >= 1.000.000`, `Silver >= 500.000`, `Reguler`).
5. **`KonversiNilai(nilai)`**: Konversi nilai angka menjadi grade huruf (`A`, `B`, `C`, `D`).

---

### D. Stored Procedures
1. **`TambahPelanggan(IN p_nama, IN p_kota)`**: Menambahkan data member baru ke `tbl_pelanggan`.
2. **`HitungPelanggan(OUT jumlah)`**: Menghitung total keseluruhan pelanggan melalui parameter output.
3. **`TampilPelanggan()`**: Menampilkan seluruh pelanggan urut ID terbaru.
4. **`CariPelanggan(IN p_keyword)`**: Melakukan pencarian data pelanggan berdasarkan nama atau kota.
5. **`sp_TambahTransaksiBaru(IN p_id_pel, IN p_id_prd, IN p_qty, IN p_disc)`**: Transaksi kasir express terpadu dalam satu prosedur.
6. **`LaporanTransaksi(IN p_tgl_mulai, IN p_tgl_selesai)`**: Menghasilkan rekap transaksi berdasarkan rentang tanggal.

---

### E. Database Triggers
1. **`trg_kurangi_stok_dan_audit`** (`AFTER INSERT ON tbl_detail_transaksi`):
   Otomatis memotong stok produk dan mencatat log audit ke `tbl_log`.
2. **`after_insert_pelanggan`** (`AFTER INSERT ON tbl_pelanggan`):
   Mencatat aktivitas `INSERT PELANGGAN` ke `log_pelanggan`.
3. **`after_update_pelanggan`** (`AFTER UPDATE ON tbl_pelanggan`):
   Merekam nama lama (`OLD.nama_pelanggan`) dan nama baru (`NEW.nama_pelanggan`) ke `log_perubahan_pelanggan`.
4. **`after_delete_pelanggan`** (`AFTER DELETE ON tbl_pelanggan`):
   Mencatat aktivitas `DELETE PELANGGAN` ke `log_pelanggan`.
5. **`before_insert_transaksi`** (`BEFORE INSERT ON tbl_transaksi`):
   Memvalidasi jika nilai `total_belanja < 0`, otomatis melempar error `SIGNAL SQLSTATE '45000'`.

---

## 💻 Panduan Instalasi & Penggunaan

### 1. Persyaratan Sistem
- Web Server: **Apache (XAMPP / Laragon)**
- Bahasa Pemrograman: **PHP 7.4 / 8.x**
- Database Server: **MariaDB 10.4+ / MySQL 8.0+**
- Browser: Google Chrome, Microsoft Edge, Firefox

### 2. Langkah Pemasangan
1. Pastikan folder proyek berada di direktori web server:
   ```
   c:\xampp\htdocs\tokokita
   ```
2. Jalankan service **Apache** dan **MySQL** melalui XAMPP Control Panel.
3. Buka browser dan jalankan script installer otomatis:
   ```
   http://localhost/tokokita/setup.php?action=install
   ```
   *Script `setup.php` akan otomatis membuat database `db_tokokita`, seluruh tabel, function, view, procedure, trigger, dan sample data.*

4. Buka halaman utama:
   ```
   http://localhost/tokokita/login.php
   ```

---

## 🔑 Akun Demo Bawaan

| Peran (Role) | Username | Password | Keterangan |
|---|---|---|---|
| **Super Admin** | `admin` | `admin123` | Hak akses penuh kontrol sistem & laporan |
| **Kasir POS** | `kasir` | `kasir123` | Antrean kasir, proses POS, struk, dan topup |
| **Pelanggan** | `budi` | `user123` | Member belanja & cek saldo dompet digital |

---

## 📁 Struktur Berkas Proyek

```
tokokita/
├── README.md               # Dokumentasi lengkap sistem TokoKita
├── setup.php               # Auto-installer database, tabel, rutin SQL, & sample data
├── register.php            # Form pendaftaran akun & member baru
├── login.php               # Halaman autentikasi & login pengguna
├── logout.php              # Proses keluar sistem & penghapusan session
├── navbar.php              # Navigasi sticky responsif sistem
├── functions.php           # Helper fungsi PHP, session check, format rupiah
├── koneksi.php             # Konfigurasi koneksi MySQLi ke database
├── script.js               # JavaScript helper (kalkulasi kasir, format rupiah, filter tabel)
│
├── dashboard.php           # Dashboard statistik penjualan & ringkasan omzet
├── index.php               # Halaman kontrol utama sistem
│
├── kasir_pos.php           # Terminal kasir Point of Sale (POS)
├── kasir_pesanan.php       # Antrean pesanan masuk kasir
├── kasir_riwayat.php       # Riwayat transaksi belanja kasir
├── checkout.php            # Pembayaran multi-keranjang dengan ACID transaction
├── struk.php               # Struk thermal kasir siap cetak (@media print)
│
├── saldo.php               # Dompet digital saldo member & top-up multi-payment
├── admin_topup.php         # Panel admin kelola pengisian saldo pelanggan
├── admin_produk.php        # Panel admin kelola katalog produk
├── produk.php              # CRUD master data produk
├── kategori.php            # CRUD kategori barang
├── keranjang.php           # Keranjang belanja kasir
├── pelanggan.php           # Master data pelanggan & trigger log
├── diskon.php              # Manajemen kode voucher promosi
├── favorit.php             # Wishlist produk favorit
├── profil.php              # Profil akun pengguna aktif
├── aktivitas.php           # Pusat pemantauan log audit trigger MariaDB
└── laporan.php             # Laporan eksekutif query SQL tingkat lanjut
```

---

## 📄 Lisensi & Hak Cipta
Dikembangkan untuk pembelajaran dan implementasi **Basis Data Terapan & SQL Lanjutan**. Bebas digunakan dan dimodifikasi untuk tujuan akademik, riset, maupun pengembangan sistem Point of Sale komersial.
"# tokokita" 
# tokokita
