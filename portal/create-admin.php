<?php
declare(strict_types=1);
// Buat / perbarui akun admin dari command line.
// Cara pakai:  php create-admin.php <username> <password> [nama]
require __DIR__ . '/config.php';

if ($argc < 3) {
    fwrite(STDERR, "Cara pakai: php create-admin.php <username> <password> [nama]\n");
    exit(1);
}

$username = trim($argv[1]);
$password = $argv[2];
$nama     = $argv[3] ?? 'Admin MPI';

if ($username === '' || strlen($password) < 6) {
    fwrite(STDERR, "Username tidak boleh kosong dan password minimal 6 karakter.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$st = mpi_db()->prepare(
    'INSERT INTO mpi_users (username, password_hash, nama, role)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), nama = VALUES(nama)'
);
$st->execute([$username, $hash, $nama, 'admin']);

echo "OK — akun '{$username}' ({$nama}) dibuat/diperbarui dengan role admin.\n";
