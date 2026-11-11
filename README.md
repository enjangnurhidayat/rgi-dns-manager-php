# RGI DNS Manager — PHP Native

Migrasi dari Flask/Jinja ke PHP Native modular. Database SQLite lama dipertahankan.

## Fitur yang dimigrasikan
- Login administrator, session, dan CSRF token.
- Dashboard monitoring RouterOS API.
- Manajemen domain (tambah, aktif/nonaktif, hapus).
- Kategori bawaan: Judi Online, Konten Dewasa, Torrent.
- Sinkronisasi domain aktif menjadi DNS Static `NXDOMAIN`.
- Penanda entri milik aplikasi: `RGI-DNS-MANAGER`.
- Log aktivitas administrator.

`bot.py` pada proyek Flask asal kosong, sehingga belum ada fungsi Telegram bot yang dapat dimigrasikan.

## 1. Persyaratan
- PHP 8.1 atau lebih baru.
- Ekstensi `pdo_sqlite` / paket `php-sqlite3`.
- Ekstensi sockets standar PHP (untuk RouterOS API).
- Apache dengan mod_rewrite atau PHP built-in server.

Di Linux Mint/Ubuntu:
```bash
sudo apt update
sudo apt install php php-sqlite3
php -m | grep -E 'PDO|pdo_sqlite'
```

## 2. Konfigurasi
Salin `.env.example` menjadi `.env`:
```bash
cp .env.example .env
nano .env
```
Isi `MIKROTIK_HOST`, `MIKROTIK_PORT`, `MIKROTIK_USER`, dan `MIKROTIK_PASSWORD` sesuai konfigurasi RouterOS API yang memang diizinkan administrator jaringan.

Buat hash password admin yang kompatibel dengan PHP:
```bash
php setup_admin.php
```
Password lama di Flask memakai hash Werkzeug scrypt yang tidak kompatibel langsung dengan `password_verify()` PHP. Karena itu, buat password admin baru satu kali dengan perintah di atas.

## 3. Database
Database hasil proyek Flask disertakan di `storage/tkj35.db`. Tabel dan data yang ada dipertahankan. Jangan menghapus atau mengganti file database jika ingin mempertahankan data domain dan log.

Jika folder `storage` tidak dapat ditulis:
```bash
chmod 770 storage
```

## 4. Menjalankan untuk pengujian lokal
Dari folder proyek:
```bash
php -S 127.0.0.1:8000 -t public public/index.php
```
Buka:
`http://127.0.0.1:8000`

Gunakan alamat loopback untuk pengujian lokal. Jangan membuka aplikasi ke internet sebelum HTTPS, pembatasan akses, dan keamanan server disiapkan.

## 5. Struktur
```text
rgi-dns-manager-php/
├── app/
│   ├── config.php
│   ├── database.php
│   ├── functions.php
│   ├── RouterOSApi.php
│   ├── bootstrap.php
│   ├── pages/
│   └── partials/
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
├── storage/
│   └── tkj35.db
├── .env.example
├── setup_admin.php
└── README.md
```

## Catatan keamanan
- Jangan membagikan `.env`.
- Gunakan akun RouterOS khusus dengan hak minimum yang diperlukan.
- Sinkronisasi hanya menambah entri domain yang belum ada. Entri DNS yang sudah ada tetapi bukan milik aplikasi ditandai konflik dan tidak ditimpa.
- Penghapusan domain dari database tidak otomatis menghapus entri DNS pada MikroTik, sama seperti perilaku aplikasi Flask asal.
