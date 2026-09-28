-- ============================================================
-- Portal MPI — database & skema
-- GANTI 'GANTI_PASSWORD_KUAT' sebelum menjalankan!
-- Jalankan: sudo mysql < db.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS mpi_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'mpi_user'@'localhost'
  IDENTIFIED BY 'GANTI_PASSWORD_KUAT';

GRANT ALL PRIVILEGES ON mpi_db.* TO 'mpi_user'@'localhost';
FLUSH PRIVILEGES;

USE mpi_db;

-- -------------------- Pengguna (guru/admin) --------------------
CREATE TABLE IF NOT EXISTS mpi_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama          VARCHAR(120) NOT NULL,
  role          ENUM('admin','guru') NOT NULL DEFAULT 'guru',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------- Daftar game --------------------
CREATE TABLE IF NOT EXISTS mpi_games (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(64)  NOT NULL UNIQUE,
  nama       VARCHAR(160) NOT NULL,
  deskripsi  TEXT NULL,
  icon       VARCHAR(20)  NOT NULL DEFAULT '🎮',
  file_path  VARCHAR(255) NOT NULL DEFAULT '',
  tipe       VARCHAR(40)  NOT NULL DEFAULT 'benar_salah',
  guru_id    INT UNSIGNED NULL,
  aktif      TINYINT(1)   NOT NULL DEFAULT 1,
  urutan     INT          NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_games_guru FOREIGN KEY (guru_id) REFERENCES mpi_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------- Mata pelajaran --------------------
CREATE TABLE IF NOT EXISTS mpi_mapel (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(120) NOT NULL UNIQUE,
  guru_id     INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mapel_guru FOREIGN KEY (guru_id) REFERENCES mpi_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------- Bank soal --------------------
-- Struktur dasar A/B (cocok untuk game Benar-Salah & sejenis).
-- Kolom `payload` (JSON) untuk format game lain (Famili 100, Millionaire, dll.)
CREATE TABLE IF NOT EXISTS mpi_soal (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id     INT UNSIGNED NOT NULL,
  mapel_id    INT UNSIGNED NULL,
  pertanyaan  TEXT NOT NULL,
  opsi_a      VARCHAR(255) NOT NULL DEFAULT 'Benar',
  opsi_b      VARCHAR(255) NOT NULL DEFAULT 'Salah',
  opsi_c      VARCHAR(255) NULL,
  opsi_d      VARCHAR(255) NULL,
  kunci       VARCHAR(8) NOT NULL DEFAULT 'A',
  penjelasan  TEXT NULL,
  payload     JSON NULL,
  guru_id     INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_soal_game FOREIGN KEY (game_id) REFERENCES mpi_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_soal_mapel FOREIGN KEY (mapel_id) REFERENCES mpi_mapel(id) ON DELETE SET NULL,
  CONSTRAINT fk_soal_guru FOREIGN KEY (guru_id) REFERENCES mpi_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------- Seed 5 game --------------------
-- file_path masih placeholder; isi setelah file HTML tiap game siap.
INSERT INTO mpi_games (slug, nama, deskripsi, icon, file_path, tipe, urutan) VALUES
  ('kamera-benar-salah', 'Game Kamera Benar-Salah',
   'Siswa berdiri di zona A/B, kamera mendeteksi posisi secara otomatis.', '📷',
   'kamera-benar-salah.html', 'benar_salah', 1),
  ('famili-100', 'Kuis Famili 100',
   'Tebak jawaban terpopuler ala Famili 100.', '💯',
   'famili-100.html', 'famili100', 2),
  ('clash-of-champions', 'Kuis Clash of Champions',
   'Adu cepat antar siswa/kelompok.', '⚔️',
   'clash-of-champions.html', 'clash', 3),
  ('gesture-battle', 'Game Gesture Battle',
   'Tebak kata lewat gerakan.', '🙌',
   'gesture-battle.html', 'gesture', 4),
  ('millionaire', 'Kuis Who Wants To Be Millionaire',
   'Soal bertingkat menuju hadiah 1 miliar.', '💰',
   'millionaire.html', 'millionaire', 5)
ON DUPLICATE KEY UPDATE slug = slug;

-- -------------------- Mata pelajaran awal --------------------
INSERT INTO mpi_mapel (nama) VALUES ('Informatika')
ON DUPLICATE KEY UPDATE nama = nama;
