<?php
/**
 * TEMPLATE kredensial — SALIN file ini menjadi /var/www/mpi/secrets.php
 * (SATU tingkat DI ATAS folder public, agar tidak bisa diakses publik).
 *
 *   cp secrets.example.php ../secrets.php
 *   nano ../secrets.php
 */

// ---- Database (harus sama dengan yang dipakai di db.sql) ----
define('MPI_DB_HOST', '127.0.0.1');
define('MPI_DB_NAME', 'mpi_db');
define('MPI_DB_USER', 'mpi_user');
define('MPI_DB_PASS', 'GANTI_PASSWORD_KUAT');

// ---- Google Gemini — FITUR AI SEDANG DINONAKTIFKAN ----
// Pembuatan soal otomatis dengan AI sudah dihapus dari panel admin.
// Soal dibuat manual lewat editor Bank Soal atau impor CSV.
// Konstanta ini dibiarkan agar secrets.php lama tetap aman dimuat
// (config.php memberi nilai default bila konstanta tidak ada), dan
// siap dipakai lagi bila fitur AI diaktifkan kembali di masa depan.
define('MPI_GEMINI_KEY', '');
define('MPI_GEMINI_MODEL', 'gemini-2.0-flash');

// ---- Aplikasi ----
define('MPI_APP_URL', 'https://mpi.smp5tegal.sch.id');
