# Portal MPI — SMP 5 Tegal

Media Pembelajaran Interaktif (MPI) untuk Interactive Flat Panel (IFP), diakses di
**https://mpi.smp5tegal.sch.id**.

## Fitur
- **Beranda (menu)** berisi 5 game edukasi.
- **Login guru** + kelola game & bank soal.
- **Bank soal terpusat** dengan 4 format soal.
- **Buat soal otomatis** dengan AI (Google Gemini).
- **API soal** (`api/questions.php`) dipakai semua game.

## 5 Game
| Game | Tipe soal |
|---|---|
| 📷 Kamera Benar-Salah | A/B (deteksi pose via kamera) |
| 💰 Who Wants To Be Millionaire | Pilihan ganda 4 opsi (15 level) |
| 💯 Kuis Famili 100 | Jawaban populer + poin (hingga 6 tim) |
| ⚔️ Clash of Champions | Pilihan ganda 4 opsi (duel 2 tim) |
| 🙌 Gesture Battle | Kata + kategori (tebak gerakan) |

## Struktur repo
```
mpi-portal/          ← root repo = /var/www/mpi/ di server
├── .gitignore
├── README.md
├── FASE0-SETUP.md
├── config/
│   ├── nginx-mpi.conf
│   └── cloudflared-ingress.yml
└── portal/          ← webroot nginx (di server: /var/www/mpi/portal)
    ├── config.php
    ├── index.php / play.php / login.php / logout.php / admin.php
    ├── create-admin.php
    ├── db.sql
    ├── secrets.example.php
    ├── api/questions.php
    ├── assets/style.css
    └── game/*.html
```

> **Penting:** file `secrets.php` (berisi password DB & API key Gemini) **tidak
> di-commit** (lihat `.gitignore`). File itu dibuat manual di server pada
> `/var/www/mpi/secrets.php` (di luar webroot).

## Deploy cepat
Lihat **[FASE0-SETUP.md](FASE0-SETUP.md)**.

Repository: `https://github.com/abrisam-arsalan/mpi.git`

## Alur pengembangan (setiap ada perubahan)
1. Edit file di lokal.
2. `git add . && git commit -m "pesan" && git push`
3. Di server: `cd /var/www/mpi && git pull`
