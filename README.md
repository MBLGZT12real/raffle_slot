# 🎰 Undian Slot Acara

Aplikasi web pengundian slot berbasis PHP untuk acara/event. Sistem ini mendistribusikan brand/peserta ke dalam slot undian dengan aturan larangan pertemuan (*blacklist*) antar-brand, memastikan brand yang tidak boleh bertemu tidak berada dalam satu slot yang sama.

---

## ✨ Fitur Utama

- **Animasi pengundian** — tampilan slot machine dengan efek spin, reveal bounce, dan confetti
- **Algoritma backtracking** — menjamin solusi slot yang valid secara kombinatorik, jauh lebih efisien dari brute-force
- **Aturan blacklist** — setiap brand dapat mendefinisikan brand lain yang tidak boleh satu slot (`not_allow_brand`), berlaku mutual
- **Multi-group** — mendukung hingga 6 group brand (A–F) dalam satu undian
- **Relax Mode** — jika solusi strict tidak ditemukan, sistem mencatat *collision* dan tetap menghasilkan undian
- **Non-consecutive reorder** — post-processing otomatis agar brand yang sama tidak muncul di dua slot berurutan
- **Import Excel** — data brand dan setting diimpor dari file `.xlsx` dengan preview 2-langkah sebelum konfirmasi
- **Export Excel** — hasil undian dapat diunduh sebagai file `.xlsx` (sheet Detail Slot + Rekap Brand)
- **Dashboard** — ringkasan statistik, status tanggal, dan log aktivitas terbaru
- **Activity log** — setiap aksi undian, import, dan hapus data dicatat otomatis
- **Autentikasi** — perlindungan satu-password untuk seluruh halaman

---

## 🛠️ Teknologi

| Komponen | Detail |
|---|---|
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Frontend** | Bootstrap 5.3, Bootstrap Icons |
| **Library PHP** | [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) `^1.30` (via Composer) |
| **Server** | Apache (XAMPP / Laragon / server apapun) |

---

## 📋 Prasyarat

- **PHP** 7.4 atau lebih baru
- **MySQL / MariaDB**
- **Composer** — untuk menginstal dependensi PHP
- **Web server** dengan dukungan PHP (XAMPP, Laragon, dll.)

---

## 🚀 Instalasi & Setup

### 1. Clone repositori

```bash
git clone https://github.com/<username>/<repo>.git
cd <repo>
```

Atau unduh sebagai ZIP lalu ekstrak ke direktori web server Anda (contoh: `C:/xampp/htdocs/raffle_slot`).

---

### 2. Install dependensi PHP

```bash
composer install
```

Ini akan membuat folder `vendor/` dengan library PhpSpreadsheet.

---

### 3. Buat database

Buka **phpMyAdmin** atau klien MySQL, lalu jalankan file schema:

```sql
SOURCE database/schema.sql;
```

Atau salin isi file `database/schema.sql` dan jalankan langsung di query editor.

Script ini akan membuat database `undian_slot_acara` beserta 4 tabel:
- `table_setting` — konfigurasi slot per tanggal undian
- `table_brand` — master brand dan aturan blacklist
- `table_result` — hasil undian
- `table_log` — log aktivitas sistem

---

### 4. Konfigurasi database

Salin file contoh konfigurasi:

```bash
cp config/database.example.php config/database.php
```

Lalu edit `config/database.php` sesuai dengan kredensial database Anda:

```php
$host = "localhost";
$user = "root";       // username MySQL Anda
$pass = "";           // password MySQL Anda
$db   = "undian_slot_acara";
```

> ⚠️ File `config/database.php` sudah ada di `.gitignore` — jangan pernah di-commit ke repositori.

---

### 5. Konfigurasi password autentikasi

Edit file `config/auth.php`:

```php
define('AUTH_PASSWORD', 'ganti_dengan_password_anda');
```

---

### 6. Buat folder storage

```bash
mkdir storage
```

Folder ini digunakan untuk menyimpan file Excel sementara saat proses import preview. Folder ini sudah di-gitignore.

---

### 7. Akses aplikasi

Buka browser dan akses:

```
http://localhost/raffle_slot/public/login.php
```

Login menggunakan password yang sudah dikonfigurasi di langkah 5.

---

## 📂 Struktur Direktori

```
raffle_slot/
├── assets/
│   ├── css/
│   │   └── app.css              # Stylesheet utama (termasuk draw arena)
│   ├── images/
│   │   └── loading.gif
│   └── sounds/
│       ├── slot.wav              # Suara saat spin
│       └── win.wav               # Suara saat stop
├── config/
│   ├── auth.php                  # Konfigurasi password
│   ├── database.example.php      # Template konfigurasi DB
│   └── database.php              # ⚠️ Buat sendiri, jangan di-commit
├── core/
│   ├── BrandModel.php            # Query master brand & aturan blacklist
│   ├── DrawService.php           # Algoritma undian (backtracking + reorder)
│   ├── LogModel.php              # Pencatatan log aktivitas
│   ├── ResultModel.php           # Simpan & ambil hasil undian
│   └── SettingModel.php          # Query konfigurasi slot per tanggal
├── database/
│   └── schema.sql                # DDL lengkap semua tabel
├── export/
│   └── export_result_excel.php   # Download hasil undian (.xlsx)
├── helpers/
│   └── db.php                    # Helper koneksi & query MySQL
├── import/
│   ├── tb_brand1.xlsx            # Contoh file import brand
│   ├── tb_brand2.xlsx            # Contoh file import brand (dengan not_allow)
│   ├── tb_setting1.xlsx          # Contoh file import setting
│   └── tb_setting2.xlsx          # Contoh file import setting
├── partials/
│   ├── assets.php                # Link CSS/JS (Bootstrap, Icons)
│   ├── auth_check.php            # Middleware auth untuk halaman HTML
│   ├── auth_check_api.php        # Middleware auth untuk endpoint JSON/AJAX
│   ├── footer.php
│   └── header.php                # Navbar + offcanvas menu
├── public/
│   ├── index.php                 # 🎰 Halaman utama pengundian
│   ├── dashboard.php             # Statistik & log aktivitas
│   ├── result.php                # Lihat hasil undian per tanggal
│   ├── import_brand.php          # Import data brand dari Excel
│   ├── import_setting.php        # Import setting slot dari Excel
│   ├── login.php / logout.php    # Autentikasi
│   └── ...                       # Endpoint AJAX (save_draw, prepare_draw, dll.)
├── storage/                      # ⚠️ Buat manual, sudah di-gitignore
├── .gitignore
├── composer.json
└── README.md
```

---

## 📊 Format File Excel Import

### Import Brand (`import_brand.php`)

| Kolom | Wajib | Keterangan | Contoh |
|---|---|---|---|
| `name_brand` | ✅ | Nama brand, maks 30 karakter | `Toyota` |
| `group_brand` | ✅ | Satu huruf A–Z | `A` |
| `not_allow_brand` | ❌ | Brand yang tidak boleh satu slot, pisah koma | `Honda, Suzuki` |

> Baris pertama adalah **header** (nama kolom). Data dimulai dari baris ke-2.

**Aturan validasi slot per group:**

```
jumlah_brand × min_slot  ≤  total_slot  ≤  jumlah_brand × max_slot
```

Jika `total_slot` melebihi batas maksimum suatu group, naikkan `max_slot` atau sesuaikan jumlah brand.

---

### Import Setting (`import_setting.php`)

| Kolom | Wajib | Keterangan | Contoh |
|---|---|---|---|
| `date_slot` | ✅ | Tanggal undian format `YYYY-MM-DD` | `2026-05-10` |
| `min_slot` | ✅ | Minimal kemunculan per brand | `2` |
| `max_slot` | ✅ | Maksimal kemunculan per brand | `3` |
| `total_slot` | ✅ | Total slot undian pada tanggal tersebut | `20` |

---

## 🎮 Cara Penggunaan

1. **Import Setting** — masuk ke menu *Setting*, upload file Excel berisi jadwal undian
2. **Import Brand** — masuk ke menu *Brand*, upload file Excel berisi daftar brand dan aturan blacklist
3. **Lakukan Undian** — buka halaman utama, pilih tanggal, klik **Shuffle**, lalu klik **Stop** untuk membekukan hasil
4. **Lihat Hasil** — menu *Hasil Undian* menampilkan rekap distribusi brand per group dan detail setiap slot
5. **Export Excel** — di halaman Hasil Undian, klik tombol *Export Excel* untuk mengunduh file `.xlsx`

> **Relax Mode** (toggle di halaman utama): jika diaktifkan, undian tetap berhasil meski ada brand yang terpaksa bertemu (*collision*). Slot yang mengalami collision ditandai dengan warna kuning.

---

## ⚙️ Cara Kerja Algoritma

1. **Quota building** — setiap brand dalam satu group mendapat kuota penampilan (antara `min_slot` dan `max_slot`) secara acak hingga total kuota = `total_slot`
2. **Pool expansion** — kuota diubah menjadi array pool (brand diulang sesuai kuota), lalu di-shuffle
3. **Backtracking per slot** — untuk setiap slot, algoritma mencoba mengisi semua group secara rekursif; jika kandidat brand di satu group menyebabkan konflik dengan group lain, sistem mundur (*backtrack*) dan mencoba kandidat lain — tanpa perlu restart seluruh undian
4. **Retry** — jika backtracking gagal untuk satu slot, seluruh undian diulang (max 500 percobaan); setelah 50 kali retry di Relax Mode, collision diizinkan
5. **Non-consecutive reorder** — setelah semua slot terisi, urutan slot disusun ulang agar tidak ada brand yang sama di dua slot berurutan pada group yang sama (greedy + random restart, max 300 percobaan)

---

## 🔒 Catatan Keamanan

- Ganti `AUTH_PASSWORD` di `config/auth.php` sebelum deploy
- Jangan pernah commit `config/database.php`
- Folder `storage/` menyimpan file sementara — pastikan tidak dapat diakses langsung dari web (atau tambahkan `.htaccess` deny)

---

## 📄 Lisensi

Proyek ini bersifat terbuka untuk digunakan dan dimodifikasi sesuai kebutuhan.
