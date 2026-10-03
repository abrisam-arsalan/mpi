# Portal MPI — SMP 5 Tegal

Media Pembelajaran Interaktif (MPI) untuk Interactive Flat Panel (IFP), diakses di
**https://mpi.smp5tegal.sch.id**.

## Fitur
- **Beranda (menu)** berisi 9 game edukasi (admin bisa **on/off per game**).
- **Alur main**: klik game → **halaman setup** pilih kelas (7/8/9) & sumber soal → main.
  Game yang belum punya soal tidak akan jalan sebelum soal dipilih — tidak ada permainan kosong di depan kelas.
- **Soal demo** bawaan di setiap game, bisa dimainkan **tanpa akun guru** (tombol "Mulai — Soal Demo").
- **Peran**:
  - **Admin** — mengatur semua permainan (on/off, edit game), pengguna, dan semua bank soal.
  - **Guru** — hanya mengatur **bank soal & mata pelajaran**; kelas 7/8/9 dipakai sebagai pemisah materi (bukan rombel).
- **Bank soal terpusat** dengan beberapa format soal.
- **Cara membuat soal**: manual lewat editor Bank Soal, atau **impor CSV** dari template yang disediakan.
  (Pembuatan soal otomatis dengan AI **sedang dinonaktifkan**.)
- **API soal** (`api/questions.php`) dipakai semua game — pilihan guru dari halaman setup
  (sumber demo/guru, kelas, mapel) disimpan di session, jadi file game tidak perlu diubah.
  Bila soal guru belum tersedia, API otomatis **fallback ke soal demo** supaya permainan tetap jalan.

## Format impor CSV
Unduh template dari admin (`?dl=template`). Kolom:

```
soal;opsiA;opsiB;opsiC;opsiD;kunci;penjelasan
```

- `kunci` boleh huruf `A`/`B`/`C`/`D` **atau** teks persis opsi yang benar.
- Format lama khusus game A/B juga masih dikenali: `soal;opsiA;opsiB;kunci;penjelasan`.
- Pemisah bisa `;` atau `,`. Baris pertama berupa judul kolom akan dilewati.

## 9 Game
| Game | Slug | Tipe | Cara main singkat |
|---|---|---|---|
| 📷 Kamera Benar-Salah | `kamera-benar-salah` | A/B | Siswa berdiri di zona A/B, kamera mendeteksi posisi. |
| 💯 Family 100 | `famili-100` | Jawaban + poin | Dua tim tebak 5 jawaban survei; 3X = giliran berpindah. |
| ⚔️ Class of Champions | `clash-of-champions` | PG 4 opsi | Papan 40 kotak, rebutan, kirim jawaban ≤10 detik, peringkat otomatis. |
| 🙌 Motion Quest AR (Gesture Battle) | `gesture-battle` | PG 4 opsi | Arahkan tangan ke gelembung jawaban yang turun; benar meletus hijau, salah merah. |
| 💰 Who Wants To Be Millionaire | `millionaire` | PG 4 opsi | 15 tingkat menuju 1 miliar, 3 bantuan, titik aman tingkat 5 & 10. |
| 🏎️ Edu Racing | `edu-racing` | PG 4 opsi | Rebutan soal; jawaban benar membuat mobil melaju + poin. |
| 🎁 Mystery Box | `mystery-box` | PG 4 opsi | 40 kotak rahasia, pilih level Mudah/Sedang/Sulit, 30 detik. |
| ⭕ XXO Edukasi | `xxo-edukasi` | PG 4 opsi | Rebut kotak, jawab soal, susun 3 kotak berurutan, nyawa & power-up. |
| 🐍 Snake Quiz | `snake-quiz` | PG 4 opsi | Dua ular satu arena (WASD vs panah), kejar makanan, jawab soal. |

> Game berkamera (`kamera-benar-salah`, `gesture-battle`) memproses video **hanya di perangkat** — tidak ada data yang dikirim ke server.

## Migrasi server
- Alur baru pilih soal (kolom `kelas` & `is_demo` + seed soal demo): `cd /var/www/mpi/portal && sudo mysql mpi_db < migration-v4.sql`
- Upgrade dari 5 game lama: `cd /var/www/mpi/portal && sudo mysql mpi_db < migration-v3.sql`
- Upgrade skema lama (mapel & peran guru): `sudo mysql mpi_db < migration-v2.sql`

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
    ├── db.sql                     ← seed 9 game (instalasi baru)
    ├── migration-v2.sql           ← upgrade skema lama
    ├── migration-v3.sql           ← tambah 4 game baru + nama/deskripsi baru
    ├── migration-v4.sql           ← kolom kelas/is_demo + seed soal demo
    ├── secrets.example.php
    ├── api/questions.php
    ├── assets/style.css
    └── game/                      ← 1 file HTML mandiri per game
        ├── kamera-benar-salah.html
        ├── famili-100.html
        ├── clash-of-champions.html
        ├── gesture-battle.html
        ├── millionaire.html
        ├── edu-racing.html
        ├── mystery-box.html
        ├── xxo-edukasi.html
        └── snake-quiz.html
```

> **Penting:** file `secrets.php` (berisi password DB) **tidak
> di-commit** (lihat `.gitignore`). File itu dibuat manual di server pada
> `/var/www/mpi/secrets.php` (di luar webroot).

## Deploy cepat
Lihat **[FASE0-SETUP.md](FASE0-SETUP.md)**.

Repository: `https://github.com/abrisam-arsalan/mpi.git`

## Alur pengembangan (setiap ada perubahan)
1. Edit file di lokal.
2. `git add . && git commit -m "pesan" && git push`
3. Di server: `cd /var/www/mpi && git pull`
