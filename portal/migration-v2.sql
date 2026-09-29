-- ============================================================
-- MIGRASI v2 — tambah Mata Pelajaran + peran guru
-- Jalankan SEKALI di server (karena DB versi lama sudah terpasang):
--   cd /var/www/mpi/portal && sudo mysql mpi_db < migration-v2.sql
-- ============================================================

USE mpi_db;

CREATE TABLE IF NOT EXISTS mpi_mapel (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(120) NOT NULL UNIQUE,
  guru_id     INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mapel_guru FOREIGN KEY (guru_id) REFERENCES mpi_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE mpi_soal
  ADD COLUMN mapel_id INT UNSIGNED NULL AFTER game_id,
  ADD CONSTRAINT fk_soal_mapel FOREIGN KEY (mapel_id) REFERENCES mpi_mapel(id) ON DELETE SET NULL;

INSERT INTO mpi_mapel (nama) VALUES ('Informatika')
ON DUPLICATE KEY UPDATE nama = nama;
