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

// ---- Google Gemini (AI Studio) ----
// Kosongkan bila belum mau pakai fitur "Buat Soal dengan AI".
define('MPI_GEMINI_KEY', '');
define('MPI_GEMINI_MODEL', 'gemini-2.0-flash');

// ---- Aplikasi ----
define('MPI_APP_URL', 'https://mpi.smp5tegal.sch.id');
