-- ============================================================
-- MIGRASI v4 — Alur baru "pilih soal sebelum main"
-- 1) Kolom `kelas` (pemisah materi: 7/8/9) & `is_demo` di mpi_soal
-- 2) Seed soal demo untuk semua game (is_demo = 1, berlaku semua kelas)
-- Jalankan SEKALI di server:
--   cd /var/www/mpi/portal && sudo mysql mpi_db < migration-v4.sql
-- Aman dijalankan ulang (bagian 1 idempoten; bagian 2 di-guard).
-- ============================================================

USE mpi_db;

-- ------------------------------------------------------------
-- 1) Skema baru
-- ------------------------------------------------------------
-- MariaDB mendukung ADD COLUMN IF NOT EXISTS (idempoten).
-- (Untuk MySQL 8 yang tidak mendukungnya, hapus klausa IF NOT EXISTS
--  karena migrasi ini hanya perlu dijalankan sekali.)
ALTER TABLE mpi_soal
  ADD COLUMN IF NOT EXISTS kelas   VARCHAR(2) NULL DEFAULT NULL AFTER mapel_id,
  ADD COLUMN IF NOT EXISTS is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER payload;
-- kelas NULL = berlaku untuk semua kelas (7, 8, dan 9).

-- ------------------------------------------------------------
-- 2) Seed soal demo (is_demo = 1)
--    Soal demo dipakai tombol "Mulai — Soal Demo" di halaman setup,
--    sehingga siapa pun bisa mencoba game tanpa akun guru.
--    game_id dicari lewat slug agar aman terhadap perbedaan urutan id.
-- ------------------------------------------------------------
SET @sudah_demo := (SELECT COUNT(*) FROM mpi_soal WHERE is_demo = 1);

-- ---------- Edu Racing (PG) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Ibu kota Provinsi Jawa Tengah adalah …', 'Semarang', 'Surabaya', 'Bandung', 'Solo', 'A', 'Ibu kota Jawa Tengah adalah Semarang.', 1 FROM mpi_games WHERE slug = 'edu-racing' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Hasil dari 15 × 4 adalah …', '45', '60', '55', '65', 'B', '15 × 4 = 60.', 1 FROM mpi_games WHERE slug = 'edu-racing' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Planet yang paling dekat dengan Matahari adalah …', 'Venus', 'Bumi', 'Merkurius', 'Mars', 'C', 'Merkurius adalah planet terdekat dari Matahari.', 1 FROM mpi_games WHERE slug = 'edu-racing' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Lambang unsur besi dalam tabel periodik adalah …', 'Au', 'Ag', 'Fe', 'Cu', 'C', 'Fe (ferrum) adalah lambang unsur besi.', 1 FROM mpi_games WHERE slug = 'edu-racing' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Kalimat berikut yang menggunakan ejaan baku adalah …', 'Dia pergi ke sekolah.', 'Dia pergi kesekolah.', 'Dia pergi ke sekolah kemaren.', 'Dia pigi ke sekolah.', 'A', 'Kata depan "ke" ditulis terpisah dan ejaan harus baku.', 1 FROM mpi_games WHERE slug = 'edu-racing' AND @sudah_demo = 0;

-- ---------- Class of Champions (PG) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Hasil dari 144 ÷ 12 adalah …', '12', '14', '16', '11', 'A', '144 ÷ 12 = 12.', 1 FROM mpi_games WHERE slug = 'clash-of-champions' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Proklamasi Kemerdekaan Indonesia dibacakan pada tanggal …', '1 Juni 1945', '17 Agustus 1945', '10 November 1945', '28 Oktober 1928', 'B', 'Proklamasi dibacakan Ir. Soekarno pada 17 Agustus 1945.', 1 FROM mpi_games WHERE slug = 'clash-of-champions' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Organ utama pernapasan manusia adalah …', 'Jantung', 'Hati', 'Paru-paru', 'Ginjal', 'C', 'Paru-paru adalah organ utama pernapasan.', 1 FROM mpi_games WHERE slug = 'clash-of-champions' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Luas persegi dengan sisi 7 cm adalah …', '14 cm²', '28 cm²', '49 cm²', '21 cm²', 'C', 'Luas persegi = sisi × sisi = 7 × 7 = 49 cm².', 1 FROM mpi_games WHERE slug = 'clash-of-champions' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Satuan daya listrik adalah …', 'Volt', 'Ampere', 'Watt', 'Ohm', 'C', 'Daya listrik diukur dalam watt.', 1 FROM mpi_games WHERE slug = 'clash-of-champions' AND @sudah_demo = 0;

-- ---------- Motion Quest AR / Gesture Battle (PG, kategori opsional) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, payload, is_demo)
SELECT id, 'Hewan berikut yang bisa terbang adalah …', 'Kucing', 'Ayam', 'Ikan', 'Kambing', 'B', 'Ayam memiliki sayap dan dapat terbang rendah.', JSON_OBJECT('kategori', 'Hewan'), 1 FROM mpi_games WHERE slug = 'gesture-battle' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, payload, is_demo)
SELECT id, 'Kegiatan memecah masalah besar menjadi bagian kecil disebut …', 'Abstraksi', 'Dekomposisi', 'Algoritma', 'Pola', 'B', 'Dekomposisi = memecah masalah menjadi bagian kecil.', JSON_OBJECT('kategori', 'Berpikir Komputasional'), 1 FROM mpi_games WHERE slug = 'gesture-battle' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Lambang negara Indonesia adalah …', 'Garuda Pancasila', 'Komodo', 'Elang Jawa', 'Rajawali', 'A', 'Lambang negara RI adalah Garuda Pancasila.', 1 FROM mpi_games WHERE slug = 'gesture-battle' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Warna hasil campuran kuning dan biru adalah …', 'Hijau', 'Ungu', 'Oranye', 'Merah', 'A', 'Kuning + biru = hijau.', 1 FROM mpi_games WHERE slug = 'gesture-battle' AND @sudah_demo = 0;

-- ---------- Millionaire (PG, urut mudah → sulit: id menentukan level) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Warna bendera Indonesia adalah …', 'Merah putih', 'Merah biru', 'Putih hijau', 'Kuning merah', 'A', 'Bendera Indonesia berwarna merah putih.', 1 FROM mpi_games WHERE slug = 'millionaire' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Hasil dari 9 × 8 adalah …', '63', '72', '81', '64', 'B', '9 × 8 = 72.', 1 FROM mpi_games WHERE slug = 'millionaire' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Ibu kota Republik Indonesia adalah …', 'Bandung', 'Surabaya', 'Jakarta', 'Medan', 'C', 'Jakarta adalah ibu kota Indonesia.', 1 FROM mpi_games WHERE slug = 'millionaire' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Mata uang negara Jepang adalah …', 'Won', 'Yuan', 'Yen', 'Ringgit', 'C', 'Mata uang Jepang adalah yen.', 1 FROM mpi_games WHERE slug = 'millionaire' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Rumus Pythagoras pada segitiga siku-siku adalah …', 'a² + b² = c²', 'a × b = c', 'a + b = c²', 'a² − b² = c', 'A', 'Teorema Pythagoras: kuadrat hipotenusa = jumlah kuadrat sisi lainnya.', 1 FROM mpi_games WHERE slug = 'millionaire' AND @sudah_demo = 0;

-- ---------- Mystery Box (PG) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Manakah yang termasuk perubahan wujud mengendap?', 'Es mencair', 'Air menjadi uap', 'Uap air menjadi titik-titik air', 'Gula larut dalam air', 'C', 'Mengembun (kondensasi) adalah uap berubah menjadi titik air.', 1 FROM mpi_games WHERE slug = 'mystery-box' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Tokoh yang dijuluki "Pahlawan Proklamator" bersama Soekarno adalah …', 'Soeharto', 'Moh. Hatta', 'Sudirman', 'Kartini', 'B', 'Dr. Moh. Hatta adalah proklamator bersama Ir. Soekarno.', 1 FROM mpi_games WHERE slug = 'mystery-box' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Bilangan berikut yang merupakan bilangan prima adalah …', '9', '11', '15', '21', 'B', '11 hanya habis dibagi 1 dan 11.', 1 FROM mpi_games WHERE slug = 'mystery-box' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Keanekaragaman hayati adalah perbedaan jenis makhluk hidup yang hidup di …', 'Kebun saja', 'Bumi', 'Laut saja', 'Hutan saja', 'B', 'Keanekaragaman hayati mencakup seluruh makhluk hidup di bumi.', 1 FROM mpi_games WHERE slug = 'mystery-box' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Alat pencerna yang menghasilkan enzim salivary adalah …', 'Lambung', 'Usus halus', 'Mulut', 'Hati', 'C', 'Kelenjar di mulut menghasilkan enzim salivary (amilase).', 1 FROM mpi_games WHERE slug = 'mystery-box' AND @sudah_demo = 0;

-- ---------- XXO Edukasi (PG) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Sumpah Pemuda diperingati setiap tanggal …', '17 Agustus', '28 Oktober', '10 November', '1 Juni', 'B', 'Sumpah Pemuda diperingati 28 Oktober 1928.', 1 FROM mpi_games WHERE slug = 'xxo-edukasi' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Hasil dari 25 + 5 × 3 adalah …', '90', '40', '35', '45', 'B', 'Perkalian didahulukan: 5 × 3 = 15, lalu 25 + 15 = 40.', 1 FROM mpi_games WHERE slug = 'xxo-edukasi' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Gas yang dibutuhkan manusia untuk bernapas adalah …', 'Nitrogen', 'Karbon dioksida', 'Oksigen', 'Helium', 'C', 'Manusia bernapas mengambil oksigen dari udara.', 1 FROM mpi_games WHERE slug = 'xxo-edukasi' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Lembaga negara yang berwenang membuat undang-undang adalah …', 'MPR', 'DPR', 'MA', 'MK', 'B', 'DPR berwenang membentuk undang-undang.', 1 FROM mpi_games WHERE slug = 'xxo-edukasi' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Pantun berikut: "Jalan-jalan ke kota Blitar, jangan lupa membeli sukun." Kalimat peng isi pantun adalah …', 'Baris 1 dan 2', 'Baris 2 dan 3', 'Baris 3 dan 4', 'Semua baris', 'C', 'Isi pantun terletak pada baris ketiga dan keempat.', 1 FROM mpi_games WHERE slug = 'xxo-edukasi' AND @sudah_demo = 0;

-- ---------- Snake Quiz (PG) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Hewan berikut yang merupakan mamalia adalah …', 'Buaya', 'Lumba-lumba', 'Penyu', 'Kodok', 'B', 'Lumba-lumba menyusui anaknya, jadi termasuk mamalia.', 1 FROM mpi_games WHERE slug = 'snake-quiz' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Hasil dari 2³ adalah …', '6', '8', '9', '5', 'B', '2³ = 2 × 2 × 2 = 8.', 1 FROM mpi_games WHERE slug = 'snake-quiz' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Kebudayaan Indonesia berbasis ketuhanan tercantum dalam Pancasila sila …', 'Pertama', 'Kedua', 'Ketiga', 'Keempat', 'A', 'Sila pertama: Ketuhanan Yang Maha Esa.', 1 FROM mpi_games WHERE slug = 'snake-quiz' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Lapisan udara yang melindungi bumi dari sinar UV adalah …', 'Ozon', 'Nitrogen', 'Karbon', 'Hidrogen', 'A', 'Lapisan ozon menyerap radiasi ultraviolet.', 1 FROM mpi_games WHERE slug = 'snake-quiz' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Kata tanya untuk menanyakan tempat adalah …', 'Siapa', 'Kapan', 'Di mana', 'Mengapa', 'C', '"Di mana" digunakan untuk menanyakan tempat.', 1 FROM mpi_games WHERE slug = 'snake-quiz' AND @sudah_demo = 0;

-- ---------- Kamera Benar-Salah (format A/B) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Semut termasuk hewan serangga.', 'Benar', 'Salah', '', '', 'A', 'Semut memiliki tiga pasang kaki dan badan tiga bagian (serangga).', 1 FROM mpi_games WHERE slug = 'kamera-benar-salah' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Matahari mengelilingi Bumi.', 'Benar', 'Salah', '', '', 'B', 'Bumi yang mengelilingi Matahari.', 1 FROM mpi_games WHERE slug = 'kamera-benar-salah' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Air mendidih pada suhu 100°C pada tekanan normal.', 'Benar', 'Salah', '', '', 'A', 'Pada tekanan 1 atmosfer, air mendidih pada 100°C.', 1 FROM mpi_games WHERE slug = 'kamera-benar-salah' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, is_demo)
SELECT id, 'Angka 7 adalah bilangan prima.', 'Benar', 'Salah', '', '', 'A', '7 hanya habis dibagi 1 dan 7.', 1 FROM mpi_games WHERE slug = 'kamera-benar-salah' AND @sudah_demo = 0;

-- ---------- Famili 100 (format jawaban survei) ----------
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, kunci, payload, is_demo)
SELECT id, 'Alat kebersihan yang sering dibawa saat piket kelas?', 'Benar', 'Salah', 'A',
  JSON_OBJECT('jawaban', JSON_ARRAY(
    JSON_OBJECT('teks', 'Sapu', 'poin', 40),
    JSON_OBJECT('teks', 'Pel', 'poin', 25),
    JSON_OBJECT('teks', 'Lap', 'poin', 15),
    JSON_OBJECT('teks', 'Tempat sampah', 'poin', 12),
    JSON_OBJECT('teks', 'Kain', 'poin', 8)
  )), 1
FROM mpi_games WHERE slug = 'famili-100' AND @sudah_demo = 0;
INSERT INTO mpi_soal (game_id, pertanyaan, opsi_a, opsi_b, kunci, payload, is_demo)
SELECT id, 'Makanan khas Indonesia yang terkenal?', 'Benar', 'Salah', 'A',
  JSON_OBJECT('jawaban', JSON_ARRAY(
    JSON_OBJECT('teks', 'Rendang', 'poin', 35),
    JSON_OBJECT('teks', 'Sate', 'poin', 30),
    JSON_OBJECT('teks', 'Gado-gado', 'poin', 15),
    JSON_OBJECT('teks', 'Nasi goreng', 'poin', 12),
    JSON_OBJECT('teks', 'Soto', 'poin', 8)
  )), 1
FROM mpi_games WHERE slug = 'famili-100' AND @sudah_demo = 0;
