<?php
declare(strict_types=1);

// ============================================================
// Bootstrap Portal MPI — kredensial, session, DB, helper, auth
// ============================================================

// ---------- 1. Kredensial (di luar webroot) ----------
$secretsFile = dirname(__DIR__) . '/secrets.php';
if (!is_file($secretsFile)) {
    http_response_code(500);
    exit('Konfigurasi belum lengkap: buat file <b>' . htmlspecialchars($secretsFile) . '</b> (salin dari secrets.example.php).');
}
require $secretsFile;

foreach (['MPI_DB_HOST', 'MPI_DB_NAME', 'MPI_DB_USER', 'MPI_DB_PASS', 'MPI_GEMINI_KEY', 'MPI_GEMINI_MODEL', 'MPI_APP_URL'] as $c) {
    if (!defined($c)) { define($c, ''); }
}

// ---------- 2. Session (hanya untuk web, bukan CLI) ----------
if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $isHttps,
        ]);
        session_start();
    }
}

// ---------- 3. Database ----------
function mpi_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . MPI_DB_HOST . ';dbname=' . MPI_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, MPI_DB_USER, MPI_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ---------- 4. Helper umum ----------
function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function mpi_redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}
function mpi_flash(string $msg, string $type = 'ok'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function mpi_take_flash(): ?array {
    if (empty($_SESSION['flash'])) { return null; }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- 5. Auth ----------
function mpi_user(): ?array {
    if (empty($_SESSION['uid'])) { return null; }
    static $u = null;
    if ($u === null) {
        $st = mpi_db()->prepare('SELECT * FROM mpi_users WHERE id = ?');
        $st->execute([(int) $_SESSION['uid']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}
function mpi_require_login(): void {
    if (!mpi_user()) { mpi_redirect('login.php'); }
}

// ---------- 6. CSRF ----------
function mpi_csrf(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function mpi_csrf_check(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        exit('Token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}
