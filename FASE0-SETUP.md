# FASE 0 — Deploy Portal MPI via GitHub (mpi.smp5tegal.sch.id)

Model deploy: **kode tersimpan di GitHub → server tinggal `git pull`**.
Setiap ada perubahan: edit di lokal → push ke GitHub → `git pull` di server.

Server: Ubuntu 26.04 LTS · Nginx 1.28 · PHP 8.5-FPM · MariaDB 11.8 · Cloudflare Tunnel.

## Struktur repo = struktur folder di server

```
/var/www/mpi/              ← root repo (hasil git clone)
├── .gitignore
├── README.md
├── FASE0-SETUP.md
├── config/
│   ├── nginx-mpi.conf
│   └── cloudflared-ingress.yml
├── portal/                ← webroot nginx
│   ├── config.php
│   ├── index.php, play.php, login.php, logout.php, admin.php
│   ├── create-admin.php
│   ├── db.sql
│   ├── secrets.example.php
│   ├── api/questions.php
│   ├── assets/style.css
│   └── game/*.html
└── secrets.php            ← dibuat manual di sini (di luar webroot, TIDAK di-commit)
```

---

## Langkah 1 — Siapkan repo GitHub (sekali saja, dari komputer lokal)

```bash
cd mpi-portal
git init
git add .
git commit -m "Portal MPI SMP 5 Tegal: 5 game + bank soal + AI"

# Buat repo privat di GitHub lalu push (pilih salah satu cara):

# Cara A — pakai GitHub CLI
gh repo create mpi --private --source=. --push

# Cara B — manual: buat repo kosong di github.com, lalu:
git remote add origin https://github.com/abrisam-arsalan/mpi.git
git branch -M main
git push -u origin main
```

## Langkah 2 — Di server: clone repo

```bash
cd /var/www
sudo git clone https://github.com/abrisam-arsalan/mpi.git mpi
sudo chown -R $USER:www-data /var/www/mpi
sudo chmod -R 775 /var/www/mpi
```

> Bila repo privat, gunakan Personal Access Token (PAT) atau deploy key agar
> server bisa `git pull` tanpa dimintai password setiap kali.

## Langkah 3 — Buat file kredensial `secrets.php`

```bash
cp /var/www/mpi/portal/secrets.example.php /var/www/mpi/secrets.php
nano /var/www/mpi/secrets.php
```

Isi dengan password DB (sama dengan db.sql) dan API key Gemini (opsional dulu).
File ini berada **di luar webroot** dan sudah di-`.gitignore`.

## Langkah 4 — Database & user MySQL (pisah dari CBT)

```bash
cd /var/www/mpi/portal
sudo mysql < db.sql
```

> Ganti dulu `GANTI_PASSWORD_KUAT` di `db.sql` sebelum dijalankan.

## Langkah 5 — Nginx vhost

```bash
sudo cp /var/www/mpi/config/nginx-mpi.conf /etc/nginx/sites-available/mpi.smp5tegal.sch.id
sudo ln -s /etc/nginx/sites-available/mpi.smp5tegal.sch.id /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## Langkah 6 — Cloudflare Tunnel (tambah hostname)

Buka `/etc/cloudflared/config.yml`, tambah ingress untuk
`mpi.smp5tegal.sch.id` (contoh di `config/cloudflared-ingress.yml`), lalu:

```bash
sudo systemctl restart cloudflared
sudo cloudflared tunnel route dns <NAMA-TUNNEL> mpi.smp5tegal.sch.id
```

> HTTPS ditangani Cloudflare otomatis — origin (nginx) cukup `listen 80`.

## Langkah 7 — Buat akun admin guru

```bash
cd /var/www/mpi/portal
php create-admin.php admin 'PasswordKuatAnda'
```

## Langkah 8 — Uji

- `https://mpi.smp5tegal.sch.id/` → beranda + daftar game.
- `/login.php` → login guru.
- `/admin.php` → kelola game & soal.
- `/api/questions.php?game=kamera-benar-salah` → JSON soal.

---

## Alur update rutin (setiap ada perubahan kode)

```bash
# Dari komputer lokal (setelah edit file)
cd mpi-portal
git add .
git commit -m "deskripsi perubahan"
git push

# Di server
cd /var/www/mpi
git pull
```

## Verifikasi cepat bila ada masalah

```bash
systemctl status php8.5-fpm
ls -l /run/php/php8.5-fpm.sock   # pastikan sesuai fastcgi_pass di nginx
systemctl status cloudflared
cloudflared tunnel list
sudo tail -f /var/log/nginx/error.log
```

### Bila `/api/questions.php` mengembalikan HTTP 500

Penyebab paling sering: **koneksi database gagal** (kredensial di
`/var/www/mpi/secrets.php` tidak cocok), atau **path `require` salah**.
`questions.php` ada di subfolder `api/`, jadi harus memakai
`require dirname(__DIR__) . '/config.php';` — bukan `__DIR__ . '/config.php'`
(yang menunjuk ke `portal/api/config.php` dan tidak ada).

Cek cepat:

```bash
cd /var/www/mpi/portal
php -r 'require dirname(__DIR__)."/secrets.php"; var_dump(MPI_DB_USER, MPI_DB_NAME);'
sudo mysql -u mpi_user -p mpi_db -e "SELECT COUNT(*) FROM mpi_games;"
curl -s -o /dev/null -w '%{http_code}\n' 'https://mpi.smp5tegal.sch.id/api/questions.php?game=famili-100'
```

Hasil yang benar: `200` dan berisi JSON `{"game":...,"soal":[...]}` dengan
`Access-Control-Allow-Origin: *`. Bila `soal` kosong, bank soal untuk game itu
memang belum diisi — game akan otomatis memakai soal bawaan.

### Setelah `git pull`, lakukan ini

```bash
cd /var/www/mpi/portal && sudo mysql mpi_db < migration-v3.sql   # 4 game baru
sudo bash /var/www/mpi/config/vendor-download.sh                 # model MediaPipe
```

> `create-admin.php` sekarang **menolak akses dari browser** (hanya bisa
> dijalankan lewat CLI). Jalankan dengan: `php create-admin.php admin 'Password'`.
