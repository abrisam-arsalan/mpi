-- ============================================================
-- MIGRASI v3 — 4 game baru + penyesuaian nama/deskripsi game lama
-- (Edu Racing, Mystery Box, XXO Edukasi, Snake Quiz)
-- Jalankan SEKALI di server:
--   cd /var/www/mpi/portal && sudo mysql mpi_db < migration-v3.sql
-- Aman dijalankan ulang (idempoten).
-- ============================================================

USE mpi_db;

-- 1) Tambah 4 game baru
INSERT INTO mpi_games (slug, nama, deskripsi, icon, file_path, tipe, urutan) VALUES
  ('edu-racing', 'Edu Racing',
   'Siapa cepat, dia melaju! Rebutan soal, jawaban benar membuat mobil melaju dan poin bertambah.', '🏎️',
   'edu-racing.html', 'racing', 6),
  ('mystery-box', 'Mystery Box',
   '40 kotak rahasia. Pilih kotak, jawab dalam 30 detik, kumpulkan poin terbanyak.', '🎁',
   'mystery-box.html', 'mysterybox', 7),
  ('xxo-edukasi', 'XXO Edukasi',
   'Rebut kotak, jawab soal, susun 3 kotak berurutan. Punya nyawa dan power-up.', '⭕',
   'xxo-edukasi.html', 'xxo', 8),
  ('snake-quiz', 'Snake Quiz',
   'Dua ular satu arena. Kejar makanan, jawab soal, hindari tabrakan.', '🐍',
   'snake-quiz.html', 'snake', 9)
ON DUPLICATE KEY UPDATE
  nama = VALUES(nama), deskripsi = VALUES(deskripsi), icon = VALUES(icon),
  file_path = VALUES(file_path), tipe = VALUES(tipe), urutan = VALUES(urutan), aktif = 1;

-- 2) Perbarui nama & deskripsi game lama agar sesuai alur baru
UPDATE mpi_games SET
  nama = 'Kuis Family 100',
  deskripsi = 'Dua tim, 5 jawaban survei. Jawab benar kotak terbuka, 3X giliran berpindah.'
WHERE slug = 'famili-100';

UPDATE mpi_games SET
  nama = 'Class of Champions',
  deskripsi = '2-8 tim, papan 40 soal. Rebutan, kirim jawaban dalam 10 detik, peringkat otomatis.'
WHERE slug = 'clash-of-champions';

UPDATE mpi_games SET
  nama = 'Motion Quest AR (Gesture Battle)',
  deskripsi = 'Arahkan tangan ke gelembung jawaban. Benar meletus hijau, salah meletus merah. Ada combo, power move, dan final boss.'
WHERE slug = 'gesture-battle';

UPDATE mpi_games SET
  nama = 'Who Wants To Be Millionaire',
  deskripsi = '15 tingkat menuju 1 miliar. 3 bantuan: 50:50, Tanya Penonton, Telepon Teman.'
WHERE slug = 'millionaire';

-- 3) Tipe game baru sudah tercakup di kolom VARCHAR(40) — tidak perlu ubah struktur tabel.
SELECT id, slug, nama, tipe, urutan FROM mpi_games WHERE aktif = 1 ORDER BY urutan, id;
