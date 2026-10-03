<?php
require __DIR__ . '/config.php';
mpi_require_login();
$user = mpi_user();
$isAdmin = mpi_is_admin();
$db = mpi_db();

// ============================================================
// Helper parser CSV bank soal
// (Pembuatan soal otomatis dengan AI sudah DINONAKTIFKAN — soal dibuat
//  manual lewat editor Bank Soal atau diimpor dari template CSV.)
// ============================================================
function mpi_parse_csv_soal(string $text): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    if (!$lines) { throw new Exception('File kosong.'); }
    $delim = (substr_count($lines[0], ';') >= 3) ? ';' : ',';
    $rows = []; foreach ($lines as $l) { $rows[] = str_getcsv($l, $delim); }
    // Kenali bentuk file: 4 opsi (soal;A;B;C;D;kunci;penjelasan) atau lama A/B (soal;A;B;kunci;penjelasan).
    $four = false;
    if ($rows) {
        $head = array_map(function ($c) { return strtolower(trim((string) $c)); }, $rows[0]);
        if (in_array('opsic', $head, true) || in_array('opsi_c', $head, true) || in_array('opsid', $head, true) || in_array('opsi_d', $head, true)) { $four = true; }
        if (!$four && preg_match('/soal/i', (string) ($rows[0][0] ?? ''))) { $four = count($head) >= 6; }
    }
    if ($rows && preg_match('/soal/i', (string) ($rows[0][0] ?? ''))) { array_shift($rows); }
    $out = [];
    foreach ($rows as $r) {
        $q = trim($r[0] ?? ''); if ($q === '') { continue; }
        if ($four) {
            $A = trim($r[1] ?? ''); $B = trim($r[2] ?? ''); $C = trim($r[3] ?? ''); $D = trim($r[4] ?? '');
            if ($A === '' || $B === '') { throw new Exception('Opsi A dan B wajib diisi: "' . mb_substr($q, 0, 40) . '"'); }
            $list = [$A, $B, $C, $D];
            $k = strtoupper(trim($r[5] ?? '')); $penjelasan = trim($r[6] ?? '');
        } else {
            $A = trim($r[1] ?? '') ?: 'Benar'; $B = trim($r[2] ?? '') ?: 'Salah';
            $list = [$A, $B, '', ''];
            $k = strtoupper(trim($r[3] ?? '')); $penjelasan = trim($r[4] ?? '');
        }
        // Kunci boleh berupa huruf (A/B/C/D) atau teks opsi itu sendiri.
        $letters = ['A', 'B', 'C', 'D'];
        if (in_array($k, $letters, true)) {
            if ($k === 'C' && $list[2] === '') { throw new Exception('Kunci C tapi opsi C kosong: "' . mb_substr($q, 0, 40) . '"'); }
            if ($k === 'D' && $list[3] === '') { throw new Exception('Kunci D tapi opsi D kosong: "' . mb_substr($q, 0, 40) . '"'); }
        } else {
            $norm = function (string $s): string { return trim(preg_replace('/\s+/', ' ', mb_strtolower($s))); };
            $kn = $norm($k); $found = '';
            foreach ($letters as $i => $L) {
                $v = $norm($list[$i]);
                if ($v !== '' && $kn !== '' && $v === $kn) { $found = $L; break; }
            }
            if ($found === '') { throw new Exception('Kunci tidak dikenali: "' . mb_substr($q, 0, 40) . '"'); }
            $k = $found;
        }
        $out[] = ['pertanyaan' => $q, 'opsi_a' => $list[0], 'opsi_b' => $list[1], 'opsi_c' => $list[2], 'opsi_d' => $list[3], 'kunci' => $k, 'penjelasan' => $penjelasan];
    }
    if (!$out) { throw new Exception('Tidak ada soal terbaca dari file.'); }
    return $out;
}
function mpi_parse_famili100(string $text): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    $out = [];
    foreach ($lines as $l) { $p = array_map('trim', explode(';', $l, 2)); if ($p[0] === '') { continue; } $out[] = ['teks' => $p[0], 'poin' => (int) ($p[1] ?? 0)]; }
    if (!$out) { throw new Exception('Jawaban Famili 100 wajib diisi (format: teks;poin per baris).'); }
    return $out;
}

// ============================================================
// Unduh template CSV
// ============================================================
if (isset($_GET['dl']) && $_GET['dl'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template-soal.csv"');
    echo "soal;opsiA;opsiB;opsiC;opsiD;kunci;penjelasan\n";
    echo "Manakah langkah berpikir komputasional yang memecah masalah besar jadi bagian kecil?;Abstraksi;Dekomposisi;Algoritma;Pengenalan Pola;B;Dekomposisi = memecah masalah menjadi bagian kecil\n";
    echo "Serangkaian langkah terstruktur untuk menyelesaikan masalah disebut?;Pola;Abstraksi;Algoritma;Dekomposisi;C;Algoritma adalah urutan langkah yang sistematis\n";
    echo "Berpikir komputasional hanya diperlukan oleh programmer.;Benar;Salah;;;B;Berguna untuk semua bidang\n";
    exit;
}

// ============================================================
// Proses aksi (POST)
// ============================================================
$tab = $_GET['tab'] ?? 'dashboard';
// Tab yang dikenal saja. Nilai lama 'ai' (buat soal otomatis) sudah dihapus,
// jadi otomatis dialihkan ke dashboard, bukan menampilkan halaman kosong.
if (!in_array($tab, ['dashboard', 'game', 'mapel', 'soal', 'users'], true)) { $tab = 'dashboard'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mpi_csrf_check();
    $action = $_POST['action'] ?? '';
    $redirect = $_POST['redirect'] ?? ('admin.php?tab=' . urlencode($tab));
    try {
        $adminOnly = ['game_save', 'game_delete', 'game_toggle', 'user_save', 'user_delete'];
        if (!$isAdmin && in_array($action, $adminOnly, true)) {
            throw new Exception('Akses ditolak — hanya admin yang boleh melakukan aksi ini.');
        }
        if ($action === 'game_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $d = ['slug' => trim($_POST['slug'] ?? ''), 'nama' => trim($_POST['nama'] ?? ''), 'deskripsi' => trim($_POST['deskripsi'] ?? ''), 'icon' => trim($_POST['icon'] ?? '🎮'), 'file_path' => trim($_POST['file_path'] ?? ''), 'tipe' => trim($_POST['tipe'] ?? 'benar_salah'), 'urutan' => (int) ($_POST['urutan'] ?? 0), 'aktif' => isset($_POST['aktif']) ? 1 : 0];
            if ($d['slug'] === '' || $d['nama'] === '') { throw new Exception('Slug dan nama wajib diisi.'); }
            if ($id > 0) {
                $st = $db->prepare('UPDATE mpi_games SET slug=?,nama=?,deskripsi=?,icon=?,file_path=?,tipe=?,urutan=?,aktif=? WHERE id=?');
                $st->execute([$d['slug'],$d['nama'],$d['deskripsi'],$d['icon'],$d['file_path'],$d['tipe'],$d['urutan'],$d['aktif'],$id]);
                mpi_flash('Game diperbarui.');
            } else {
                $st = $db->prepare('INSERT INTO mpi_games (slug,nama,deskripsi,icon,file_path,tipe,urutan,aktif,guru_id) VALUES (?,?,?,?,?,?,?,?,?)');
                $st->execute([$d['slug'],$d['nama'],$d['deskripsi'],$d['icon'],$d['file_path'],$d['tipe'],$d['urutan'],$d['aktif'],$user['id']]);
                mpi_flash('Game ditambahkan.');
            }
        } elseif ($action === 'game_delete') {
            $st = $db->prepare('DELETE FROM mpi_games WHERE id = ?'); $st->execute([(int) $_POST['id']]); mpi_flash('Game dihapus.');
        } elseif ($action === 'game_toggle') {
            // On/off permainan: game nonaktif hilang dari beranda & ditolak play.php.
            $st = $db->prepare('UPDATE mpi_games SET aktif = 1 - aktif WHERE id = ?'); $st->execute([(int) $_POST['id']]); mpi_flash('Status game diperbarui.');
        } elseif ($action === 'mapel_save') {
            $id = (int) ($_POST['id'] ?? 0); $nama = trim($_POST['nama'] ?? '');
            if ($nama === '') { throw new Exception('Nama mata pelajaran wajib diisi.'); }
            if ($id > 0) { $st = $db->prepare('UPDATE mpi_mapel SET nama=? WHERE id=?'); $st->execute([$nama, $id]); mpi_flash('Mata pelajaran diperbarui.'); }
            else { $st = $db->prepare('INSERT INTO mpi_mapel (nama, guru_id) VALUES (?,?)'); $st->execute([$nama, $user['id']]); mpi_flash('Mata pelajaran ditambahkan.'); }
        } elseif ($action === 'mapel_delete') {
            $st = $db->prepare('DELETE FROM mpi_mapel WHERE id = ?'); $st->execute([(int) $_POST['id']]); mpi_flash('Mata pelajaran dihapus.');
        } elseif ($action === 'soal_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $game_id = (int) $_POST['game_id'];
            $st = $db->prepare('SELECT tipe FROM mpi_games WHERE id = ?'); $st->execute([$game_id]); $gt = $st->fetchColumn() ?: 'benar_salah';
            $mapel_id = (int) ($_POST['mapel_id'] ?? 0);
            $kelas = in_array($_POST['kelas'] ?? '', ['7', '8', '9'], true) ? $_POST['kelas'] : null;
            // Centang "soal demo" hanya admin; guru null = jangan ubah status demo soal lama.
            $isDemo = $isAdmin ? (isset($_POST['is_demo']) ? 1 : 0) : null;
            $d = ['game_id' => $game_id, 'mapel_id' => ($mapel_id > 0 ? $mapel_id : null), 'pertanyaan' => trim($_POST['pertanyaan'] ?? ''), 'opsi_a' => trim($_POST['opsi_a'] ?? ''), 'opsi_b' => trim($_POST['opsi_b'] ?? ''), 'opsi_c' => trim($_POST['opsi_c'] ?? ''), 'opsi_d' => trim($_POST['opsi_d'] ?? ''), 'kunci' => strtoupper($_POST['kunci'] ?? 'A'), 'penjelasan' => trim($_POST['penjelasan'] ?? ''), 'payload' => null];
            if ($d['pertanyaan'] === '') { throw new Exception('Pertanyaan wajib diisi.'); }
            // Tipe yang mewajibkan keempat opsi terisi penuh.
            $PG_STRICT = ['millionaire','clash','racing','mysterybox','xxo','snake'];
            if ($gt === 'famili100') {
                $d['payload'] = json_encode(['jawaban' => mpi_parse_famili100($_POST['famili_jawaban'] ?? '')], JSON_UNESCAPED_UNICODE);
                $d['opsi_a'] = 'Benar'; $d['opsi_b'] = 'Salah'; $d['kunci'] = 'A';
            } else {
                if ($gt === 'gesture') { $d['payload'] = json_encode(['kategori' => trim($_POST['gest_kategori'] ?? '')], JSON_UNESCAPED_UNICODE); }
                if (!in_array($d['kunci'], ['A','B','C','D'], true)) { throw new Exception('Kunci harus A, B, C, atau D.'); }
                if (in_array($gt, $PG_STRICT, true)) { if ($d['opsi_a'] === '' || $d['opsi_b'] === '' || $d['opsi_c'] === '' || $d['opsi_d'] === '') { throw new Exception('Isi keempat opsi A, B, C, dan D.'); } }
                else { $d['opsi_a'] = $d['opsi_a'] !== '' ? $d['opsi_a'] : 'Benar'; $d['opsi_b'] = $d['opsi_b'] !== '' ? $d['opsi_b'] : 'Salah'; }
            }
            if ($id > 0) {
                $sql = 'UPDATE mpi_soal SET game_id=?,mapel_id=?,kelas=?,pertanyaan=?,opsi_a=?,opsi_b=?,opsi_c=?,opsi_d=?,kunci=?,penjelasan=?,payload=?';
                $params = [$d['game_id'],$d['mapel_id'],$kelas,$d['pertanyaan'],$d['opsi_a'],$d['opsi_b'],$d['opsi_c'],$d['opsi_d'],$d['kunci'],$d['penjelasan'],$d['payload']];
                if ($isDemo !== null) { $sql .= ',is_demo=?'; $params[] = $isDemo; }
                $sql .= ' WHERE id=?'; $params[] = $id;
                $st = $db->prepare($sql); $st->execute($params);
                mpi_flash('Soal diperbarui.');
            } else {
                $st = $db->prepare('INSERT INTO mpi_soal (game_id,mapel_id,kelas,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,kunci,penjelasan,payload,is_demo,guru_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$d['game_id'],$d['mapel_id'],$kelas,$d['pertanyaan'],$d['opsi_a'],$d['opsi_b'],$d['opsi_c'],$d['opsi_d'],$d['kunci'],$d['penjelasan'],$d['payload'],$isDemo ?? 0,$user['id']]);
                mpi_flash('Soal ditambahkan.');
            }
        } elseif ($action === 'soal_delete') {
            $st = $db->prepare('DELETE FROM mpi_soal WHERE id = ?'); $st->execute([(int) $_POST['id']]); mpi_flash('Soal dihapus.');
        } elseif ($action === 'soal_upload') {
            $game_id = (int) $_POST['game_id']; $mapel_id = (int) ($_POST['mapel_id'] ?? 0); $mapel_id = $mapel_id > 0 ? $mapel_id : null;
            $kelas = in_array($_POST['kelas'] ?? '', ['7', '8', '9'], true) ? $_POST['kelas'] : null;
            if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) { throw new Exception('Gagal mengunggah file CSV.'); }
            $items = mpi_parse_csv_soal((string) file_get_contents($_FILES['csv']['tmp_name']));
            $st = $db->prepare('INSERT INTO mpi_soal (game_id,mapel_id,kelas,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,kunci,penjelasan,guru_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($items as $it) { $st->execute([$game_id,$mapel_id,$kelas,$it['pertanyaan'],$it['opsi_a'],$it['opsi_b'],$it['opsi_c'],$it['opsi_d'],$it['kunci'],$it['penjelasan'],$user['id']]); }
            mpi_flash(count($items) . ' soal berhasil diimpor.');
        } elseif ($action === 'user_save') {
            $id = (int) ($_POST['id'] ?? 0); $username = trim($_POST['username'] ?? ''); $nama = trim($_POST['nama'] ?? ''); $role = ($_POST['role'] ?? 'guru') === 'admin' ? 'admin' : 'guru'; $pass = $_POST['password'] ?? '';
            if ($username === '' || $nama === '') { throw new Exception('Username dan nama wajib diisi.'); }
            if ($id > 0) {
                $sql = 'UPDATE mpi_users SET username=?, nama=?, role=?'; $params = [$username, $nama, $role];
                if ($pass !== '') { $sql .= ', password_hash=?'; $params[] = password_hash($pass, PASSWORD_DEFAULT); }
                $sql .= ' WHERE id=?'; $params[] = $id; $st = $db->prepare($sql); $st->execute($params); mpi_flash('Pengguna diperbarui.');
            } else {
                if (strlen($pass) < 6) { throw new Exception('Password minimal 6 karakter.'); }
                $st = $db->prepare('INSERT INTO mpi_users (username, password_hash, nama, role) VALUES (?,?,?,?)'); $st->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $nama, $role]); mpi_flash('Pengguna ditambahkan.');
            }
        } elseif ($action === 'user_delete') {
            $id = (int) $_POST['id']; if ($id === (int) $user['id']) { throw new Exception('Tidak bisa menghapus akun sendiri.'); }
            $st = $db->prepare('DELETE FROM mpi_users WHERE id = ?'); $st->execute([$id]); mpi_flash('Pengguna dihapus.');
        }
    } catch (Throwable $ex) { mpi_flash('Error: ' . $ex->getMessage(), 'err'); }
    mpi_redirect($redirect);
}

// ============================================================
// Data untuk tampilan
// ============================================================
$games = $db->query('SELECT * FROM mpi_games ORDER BY urutan, id')->fetchAll();
$mapel = $db->query('SELECT * FROM mpi_mapel ORDER BY nama')->fetchAll();
$users = $isAdmin ? $db->query('SELECT * FROM mpi_users ORDER BY id')->fetchAll() : [];
$totalSoal = (int) $db->query('SELECT COUNT(*) c FROM mpi_soal')->fetch()['c'];
$totalUsers = count($users);

$editGame = $editMapel = $editUser = null; $editSoal = null; $editJawaban = ''; $editKategori = '';
if ($tab === 'game' && isset($_GET['edit'])) { $st = $db->prepare('SELECT * FROM mpi_games WHERE id = ?'); $st->execute([(int) $_GET['edit']]); $editGame = $st->fetch(); }
if ($tab === 'mapel' && isset($_GET['edit'])) { $st = $db->prepare('SELECT * FROM mpi_mapel WHERE id = ?'); $st->execute([(int) $_GET['edit']]); $editMapel = $st->fetch(); }
if ($tab === 'users' && isset($_GET['edit'])) { $st = $db->prepare('SELECT * FROM mpi_users WHERE id = ?'); $st->execute([(int) $_GET['edit']]); $editUser = $st->fetch(); }
if ($tab === 'soal' && isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM mpi_soal WHERE id = ?'); $st->execute([(int) $_GET['edit']]); $editSoal = $st->fetch();
    if ($editSoal && !empty($editSoal['payload'])) { $p = json_decode($editSoal['payload'], true); if (is_array($p)) { if (!empty($p['kategori'])) { $editKategori = $p['kategori']; } if (!empty($p['jawaban'])) { $lines = []; foreach ($p['jawaban'] as $j) { $lines[] = ($j['teks'] ?? '') . ';' . ($j['poin'] ?? 0); } $editJawaban = implode("\n", $lines); } } }
}
$soalFilter = (int) ($_GET['game'] ?? 0); if ($soalFilter === 0 && $games) { $soalFilter = (int) $games[0]['id']; }
$mapelFilter = (int) ($_GET['mapel'] ?? 0);
$kelasFilter = (string) ($_GET['kelas'] ?? '');
if (!in_array($kelasFilter, ['7', '8', '9', 'umum'], true)) { $kelasFilter = ''; }
$soalList = [];
if ($soalFilter > 0) {
    $sql = 'SELECT s.*, m.nama AS mapel_nama FROM mpi_soal s LEFT JOIN mpi_mapel m ON m.id = s.mapel_id WHERE s.game_id = ?'; $params = [$soalFilter];
    if ($mapelFilter > 0) { $sql .= ' AND s.mapel_id = ?'; $params[] = $mapelFilter; }
    if ($kelasFilter === 'umum') { $sql .= ' AND s.kelas IS NULL'; }
    elseif ($kelasFilter !== '') { $sql .= ' AND s.kelas = ?'; $params[] = $kelasFilter; }
    $sql .= ' ORDER BY s.id DESC LIMIT 200';
    $st = $db->prepare($sql); $st->execute($params); $soalList = $st->fetchAll();
}
$flash = mpi_take_flash();

$titles = ['dashboard' => 'Dashboard', 'game' => 'Kelola Game', 'mapel' => 'Mata Pelajaran', 'soal' => 'Bank Soal', 'users' => 'Pengguna'];
$title = $titles[$tab] ?? 'Dashboard';
$ini = '';
foreach (preg_split('/\s+/', trim($user['nama'])) as $w) { if ($w !== '') { $ini .= mb_substr($w, 0, 1); } }
$ini = mb_strtoupper(mb_substr($ini, 0, 2));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — Panel MPI</title>
<style>
/* Tema NEUBRUTALISM EDU — disamakan dgn LMS & assets/style.css.
   Nama kelas TIDAK berubah dari tema lama. */
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
[hidden] { display: none !important; }
body {
  margin: 0; font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Inter, Roboto, "Helvetica Neue", Arial, sans-serif;
  font-size: 15px; line-height: 1.5;
  background: var(--paper, #FDF6EC);
  background-image: radial-gradient(rgba(20,20,20,.06) 1.2px, transparent 1.2px);
  background-size: 22px 22px;
  color: var(--ink, #141414);
}
a { text-decoration: none; color: inherit; }
h1, h2, h3 { line-height: 1.2; font-weight: 900; letter-spacing: -0.015em; }
button, .btn {
  font-family: inherit; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  min-height: 45px; padding: 10px 17px; border-radius: var(--radius-sm, 12px);
  border: var(--line, 2.5px solid #141414); background: var(--card, #fff); color: var(--ink, #141414);
  font-size: 14px; font-weight: 900; line-height: 1.2;
  box-shadow: var(--shadow, 3px 3px 0 #141414); transition: transform .06s, box-shadow .06s;
}
button:hover, .btn:hover { transform: translate(1px, 1px); box-shadow: 2px 2px 0 #141414; }
button:active, .btn:active { transform: translate(3px, 3px); box-shadow: 0 0 0 #141414; }
.btn-primary { background: var(--mint, #7CF5C3); }
.btn-secondary { background: var(--card, #fff); }
.btn-danger { background: var(--pink, #FF8FA3); }
.btn-sm { min-height: 36px; padding: 7px 13px; font-size: 12.5px; border-radius: 10px; box-shadow: 2px 2px 0 #141414; }
.muted { color: var(--muted, #55555D); font-size: 13px; font-weight: 600; }
.admin-shell { display: flex; height: 100vh; }
.admin-sidebar {
  width: 250px; background: var(--card, #fff); border-right: 3px solid var(--ink, #141414);
  display: flex; flex-direction: column; flex-shrink: 0;
}
.admin-logo {
  padding: 20px 22px; display: flex; align-items: center; gap: 11px;
  font-weight: 900; font-size: 17px; border-bottom: 3px solid var(--ink, #141414);
}
.admin-logo .dot {
  width: 38px; height: 38px; border-radius: 10px; background: var(--yellow, #FFD43B);
  color: var(--ink, #141414); display: flex; align-items: center; justify-content: center;
  font-size: 18px; border: var(--line, 2.5px solid #141414); box-shadow: 2px 2px 0 #141414;
}
.admin-nav { flex: 1; padding: 14px 12px; display: flex; flex-direction: column; gap: 6px; overflow-y: auto; }
.admin-nav a {
  display: flex; align-items: center; gap: 12px; padding: 10px 12px;
  border: var(--line, 2.5px solid #141414); border-radius: var(--radius-sm, 12px);
  background: var(--card, #fff); color: var(--ink, #141414); font-weight: 800; font-size: 13.5px;
  box-shadow: 2px 2px 0 #141414;
}
.admin-nav a:hover { transform: translate(1px, 1px); box-shadow: 1px 1px 0 #141414; }
.admin-nav a.active { background: var(--yellow, #FFD43B); }
.admin-sidefoot { padding: 14px 16px; border-top: 3px solid var(--ink, #141414); font-size: 13px; line-height: 1.7; }
.admin-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.admin-topbar {
  height: 62px; background: var(--yellow, #FFD43B); border-bottom: 3px solid var(--ink, #141414);
  display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0;
}
.admin-topbar .title { font-size: 18px; font-weight: 900; }
.admin-topbar .right { display: flex; align-items: center; gap: 16px; font-weight: 700; }
.avatar {
  width: 38px; height: 38px; border-radius: 50%; border: var(--line, 2.5px solid #141414);
  background: var(--sky, #8ECBFF); color: var(--ink, #141414);
  display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px;
  box-shadow: 2px 2px 0 #141414;
}
.admin-content { flex: 1; overflow-y: auto; padding: 24px; }
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.stat-card {
  background: var(--card, #fff); border: 3px solid var(--ink, #141414); border-radius: var(--radius, 14px);
  box-shadow: var(--shadow, 3px 3px 0 #141414); padding: 18px; display: flex; align-items: center; gap: 14px;
}
.stat-card .ic {
  width: 48px; height: 48px; border-radius: 11px; display: flex; align-items: center; justify-content: center;
  font-size: 22px; border: var(--line, 2.5px solid #141414); flex-shrink: 0;
}
.stat-card .v { font-size: 26px; font-weight: 900; line-height: 1.1; }
.stat-card .l { font-size: 11.5px; color: var(--muted, #55555D); font-weight: 700; }
.dash-card {
  background: var(--card, #fff); border: 3px solid var(--ink, #141414); border-radius: var(--radius, 14px);
  box-shadow: var(--shadow, 3px 3px 0 #141414); margin-bottom: 20px; overflow: hidden;
}
.dash-card .head {
  padding: 13px 16px; border-bottom: 3px solid var(--ink, #141414); font-weight: 900; font-size: 15px;
  display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
}
.dash-card .body { padding: 20px; }
.form-row { margin-bottom: 13px; }
.form-row label { display: block; font-size: 12.5px; color: var(--ink, #141414); margin-bottom: 6px; font-weight: 800; }
.form-row input, .form-row select, .form-row textarea {
  width: 100%; min-height: 45px; padding: 10px 13px;
  border: var(--line, 2.5px solid #141414); border-radius: var(--radius-sm, 12px);
  background: var(--card, #fff); color: var(--ink, #141414);
  font-size: 14.5px; font-family: inherit; font-weight: 600;
}
.form-row input:focus, .form-row select:focus, .form-row textarea:focus {
  outline: none; box-shadow: 3px 3px 0 var(--yellow, #FFD43B), 3px 3px 0 1px #141414;
}
.form-row textarea { min-height: 84px; resize: vertical; }
.inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.inline .form-row { flex: 1; min-width: 160px; }
table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
th, td { text-align: left; padding: 11px 13px; border-bottom: 2px solid var(--ink, #141414); vertical-align: top; }
th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; font-weight: 900; }
tbody tr:hover { background: rgba(255, 212, 59, .22); }
tbody tr:last-child td { border-bottom: 0; }
.badge {
  display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 900;
  border: 2px solid var(--ink, #141414); background: var(--card, #fff); color: var(--ink, #141414);
}
.badge.admin { background: var(--sky, #8ECBFF); }
.alert {
  padding: 12px 16px; border-radius: var(--radius-sm, 12px); margin-bottom: 16px; font-weight: 800;
  border: 2.5px solid var(--ink, #141414); box-shadow: 2px 2px 0 #141414;
}
.alert.ok { background: var(--mint, #7CF5C3); color: var(--ink, #141414); }
.alert.err { background: var(--pink, #FF8FA3); color: var(--ink, #141414); }
.welcome {
  background: var(--yellow, #FFD43B); color: var(--ink, #141414);
  border: 3px solid var(--ink, #141414); border-radius: var(--radius, 14px);
  box-shadow: var(--shadow, 3px 3px 0 #141414); padding: 22px 26px; margin-bottom: 20px;
}
.welcome h2 { margin: 0 0 6px; } .welcome p { margin: 0; font-weight: 600; }
@media (max-width: 800px) {
  .admin-shell { flex-direction: column; height: auto; min-height: 100vh; }
  .admin-sidebar { width: 100%; border-right: 0; border-bottom: 3px solid var(--ink, #141414); }
  .admin-nav { flex-direction: row; overflow-x: auto; gap: 6px; }
  .admin-nav a { white-space: nowrap; }
}
</style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-logo"><span class="dot">🎓</span> Panel MPI</div>
    <nav class="admin-nav">
      <a href="?tab=dashboard" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
      <?php if ($isAdmin): ?><a href="?tab=game" class="<?= $tab === 'game' ? 'active' : '' ?>">🎮 Game</a><?php endif; ?>
      <a href="?tab=mapel" class="<?= $tab === 'mapel' ? 'active' : '' ?>">📚 Mata Pelajaran</a>
      <a href="?tab=soal" class="<?= $tab === 'soal' ? 'active' : '' ?>">📝 Bank Soal</a>
      <?php if ($isAdmin): ?><a href="?tab=users" class="<?= $tab === 'users' ? 'active' : '' ?>">👥 Pengguna</a><?php endif; ?>
    </nav>
    <div class="admin-sidefoot">
      <div><b><?= e($user['nama']) ?></b></div>
      <div class="muted"><?= $isAdmin ? 'Administrator' : 'Guru' ?></div>
      <div style="margin-top:6px"><a href="index.php">🏠 Beranda</a> · <a href="logout.php">Keluar</a></div>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="title"><?= e($title) ?></div>
      <div class="right">
        <span class="muted"><?= $isAdmin ? 'Administrator' : 'Guru' ?></span>
        <div class="avatar"><?= e($ini) ?></div>
      </div>
    </header>
    <main class="admin-content">
      <?php if ($flash): ?><div class="alert <?= $flash['type'] === 'err' ? 'err' : 'ok' ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

      <?php if ($tab === 'dashboard'): ?>
        <div class="welcome"><h2>Selamat datang, <?= e($user['nama']) ?> 👋</h2><p>Kelola media pembelajaran interaktif SMP 5 Tegal dari sini.</p></div>
        <div class="stat-grid">
          <div class="stat-card"><div class="ic" style="background:#FFD43B">🎮</div><div><div class="v"><?= count($games) ?></div><div class="l">Game</div></div></div>
          <div class="stat-card"><div class="ic" style="background:#8ECBFF">📚</div><div><div class="v"><?= count($mapel) ?></div><div class="l">Mata Pelajaran</div></div></div>
          <div class="stat-card"><div class="ic" style="background:#7CF5C3">📝</div><div><div class="v"><?= $totalSoal ?></div><div class="l">Soal</div></div></div>
          <?php if ($isAdmin): ?><div class="stat-card"><div class="ic" style="background:#FF8FA3">👥</div><div><div class="v"><?= $totalUsers ?></div><div class="l">Pengguna</div></div></div><?php endif; ?>
        </div>
        <div class="dash-card"><div class="head">🎮 Daftar Game</div><div class="body">
          <table><tr><th>Ikon</th><th>Nama</th><th>Tipe</th><th>Aktif</th></tr>
          <?php foreach ($games as $g): ?><tr><td style="font-size:20px"><?= e($g['icon']) ?></td><td><?= e($g['nama']) ?></td><td><span class="badge"><?= e($g['tipe']) ?></span></td><td><?php if ($isAdmin): ?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="game_toggle"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="<?= $g['aktif'] ? 'btn-danger' : 'btn-primary' ?> btn-sm"><?= $g['aktif'] ? 'Matikan' : 'Nyalakan' ?></button></form> <?php endif; ?><?= $g['aktif'] ? '✅' : '—' ?></td></tr><?php endforeach; ?>
          </table>
        </div></div>
      <?php endif; ?>

      <?php if ($tab === 'game' && $isAdmin): ?>
        <div class="dash-card"><div class="head"><?= $editGame ? '✏️ Edit Game' : '➕ Tambah Game' ?></div><div class="body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="game_save"><input type="hidden" name="id" value="<?= (int) ($editGame['id'] ?? 0) ?>">
            <div class="inline">
              <div class="form-row"><label>Nama game</label><input name="nama" required value="<?= e($editGame['nama'] ?? '') ?>"></div>
              <div class="form-row"><label>Slug (unik)</label><input name="slug" required value="<?= e($editGame['slug'] ?? '') ?>"></div>
              <div class="form-row" style="max-width:110px"><label>Ikon</label><input name="icon" value="<?= e($editGame['icon'] ?? '🎮') ?>"></div>
            </div>
            <div class="form-row"><label>Deskripsi</label><input name="deskripsi" value="<?= e($editGame['deskripsi'] ?? '') ?>"></div>
            <div class="inline">
              <div class="form-row"><label>File game (relatif ke /game/)</label><input name="file_path" value="<?= e($editGame['file_path'] ?? '') ?>"></div>
              <div class="form-row"><label>Tipe</label><select name="tipe"><?php foreach (['benar_salah','famili100','clash','gesture','millionaire','racing','mysterybox','xxo','snake'] as $t): ?><option value="<?= $t ?>" <?= ($editGame['tipe'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
              <div class="form-row" style="max-width:110px"><label>Urutan</label><input type="number" name="urutan" value="<?= (int) ($editGame['urutan'] ?? 0) ?>"></div>
            </div>
            <div class="inline" style="margin-top:4px">
              <label style="color:#64748b;font-size:14px"><input type="checkbox" name="aktif" <?= !isset($editGame) || ($editGame['aktif'] ?? 1) ? 'checked' : '' ?>> Tampilkan di beranda</label>
              <button class="btn-primary"><?= $editGame ? 'Simpan' : 'Tambah' ?></button>
              <?php if ($editGame): ?><a class="btn btn-secondary" href="?tab=game">Batal</a><?php endif; ?>
            </div>
          </form>
        </div></div>
        <div class="dash-card"><div class="head">Daftar Game</div><div class="body">
          <table><tr><th>Ikon</th><th>Nama</th><th>Slug</th><th>Tipe</th><th>Urutan</th><th>File</th><th>Aktif</th><th>Aksi</th></tr>
          <?php foreach ($games as $g): ?><tr>
            <td style="font-size:20px"><?= e($g['icon']) ?></td><td><?= e($g['nama']) ?></td><td><span class="badge"><?= e($g['slug']) ?></span></td><td><?= e($g['tipe']) ?></td><td><?= (int) $g['urutan'] ?></td><td class="muted"><?= e($g['file_path']) ?></td><td><?= $g['aktif'] ? '✅' : '—' ?></td>
            <td><div class="inline" style="align-items:center">
              <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="game_toggle"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="<?= $g['aktif'] ? 'btn-danger' : 'btn-primary' ?> btn-sm"><?= $g['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
              <a class="btn btn-secondary btn-sm" href="?tab=game&edit=<?= (int) $g['id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Hapus game ini beserta semua soalnya?')" style="display:inline"><input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="game_delete"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="btn-danger btn-sm">Hapus</button></form>
            </div></td>
          </tr><?php endforeach; ?></table>
        </div></div>
      <?php endif; ?>

      <?php if ($tab === 'mapel'): ?>
        <div class="dash-card"><div class="head"><?= $editMapel ? '✏️ Edit Mata Pelajaran' : '➕ Tambah Mata Pelajaran' ?></div><div class="body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="mapel_save"><input type="hidden" name="id" value="<?= (int) ($editMapel['id'] ?? 0) ?>">
            <div class="inline">
              <div class="form-row"><label>Nama mata pelajaran</label><input name="nama" required value="<?= e($editMapel['nama'] ?? '') ?>" placeholder="Matematika"></div>
              <button class="btn-primary"><?= $editMapel ? 'Simpan' : 'Tambah' ?></button>
              <?php if ($editMapel): ?><a class="btn btn-secondary" href="?tab=mapel">Batal</a><?php endif; ?>
            </div>
          </form>
        </div></div>
        <div class="dash-card"><div class="head">Daftar Mata Pelajaran</div><div class="body">
          <table><tr><th>#</th><th>Nama</th><th>Jumlah Soal</th><th>Aksi</th></tr>
          <?php foreach ($mapel as $i => $m): ?><tr>
            <td><?= $i + 1 ?></td><td><?= e($m['nama']) ?></td>
            <td><?php $st = $db->prepare('SELECT COUNT(*) c FROM mpi_soal WHERE mapel_id = ?'); $st->execute([$m['id']]); echo (int) $st->fetch()['c']; ?></td>
            <td><div class="inline" style="align-items:center">
              <a class="btn btn-secondary btn-sm" href="?tab=mapel&edit=<?= (int) $m['id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Hapus mata pelajaran ini?')" style="display:inline"><input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="mapel_delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn-danger btn-sm">Hapus</button></form>
            </div></td>
          </tr><?php endforeach; ?></table>
        </div></div>
      <?php endif; ?>

      <?php if ($tab === 'soal'): ?>
        <div class="dash-card"><div class="head"><?= $editSoal ? '✏️ Edit Soal' : '➕ Tambah Soal' ?></div><div class="body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="soal_save"><input type="hidden" name="id" value="<?= (int) ($editSoal['id'] ?? 0) ?>">
            <div class="inline">
              <div class="form-row"><label>Game</label><select name="game_id" id="gameSelect"><?php foreach ($games as $g): ?><option value="<?= (int) $g['id'] ?>" data-tipe="<?= e($g['tipe']) ?>" <?= ($editSoal['game_id'] ?? $soalFilter) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['icon'] . ' ' . $g['nama']) ?></option><?php endforeach; ?></select></div>
              <div class="form-row"><label>Mata pelajaran</label><select name="mapel_id"><option value="0">— tanpa mapel —</option><?php foreach ($mapel as $m): ?><option value="<?= (int) $m['id'] ?>" <?= ($editSoal['mapel_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nama']) ?></option><?php endforeach; ?></select></div>
              <div class="form-row" style="max-width:160px"><label>Kelas (pemisah materi)</label><select name="kelas"><option value="">Semua kelas</option><?php foreach (['7', '8', '9'] as $k): ?><option value="<?= $k ?>" <?= ($editSoal['kelas'] ?? '') === $k ? 'selected' : '' ?>>Kelas <?= $k ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row"><label>Pertanyaan / perintah</label><textarea name="pertanyaan" required><?= e($editSoal['pertanyaan'] ?? '') ?></textarea></div>

            <div class="soal-group" data-show="benar_salah">
              <div class="inline">
                <div class="form-row"><label>Opsi A</label><input name="opsi_a" value="<?= e($editSoal['opsi_a'] ?? 'Benar') ?>"></div>
                <div class="form-row"><label>Opsi B</label><input name="opsi_b" value="<?= e($editSoal['opsi_b'] ?? 'Salah') ?>"></div>
                <div class="form-row" style="max-width:130px"><label>Kunci</label><select name="kunci"><option value="A" <?= ($editSoal['kunci'] ?? 'A') === 'A' ? 'selected' : '' ?>>A</option><option value="B" <?= ($editSoal['kunci'] ?? 'A') === 'B' ? 'selected' : '' ?>>B</option></select></div>
              </div>
            </div>
            <div class="soal-group" data-show="millionaire,clash,gesture,racing,mysterybox,xxo,snake">
              <div class="inline">
                <div class="form-row"><label>Opsi A</label><input name="opsi_a" value="<?= e($editSoal['opsi_a'] ?? '') ?>"></div>
                <div class="form-row"><label>Opsi B</label><input name="opsi_b" value="<?= e($editSoal['opsi_b'] ?? '') ?>"></div>
              </div>
              <div class="inline">
                <div class="form-row"><label>Opsi C</label><input name="opsi_c" value="<?= e($editSoal['opsi_c'] ?? '') ?>"></div>
                <div class="form-row"><label>Opsi D</label><input name="opsi_d" value="<?= e($editSoal['opsi_d'] ?? '') ?>"></div>
                <div class="form-row" style="max-width:130px"><label>Kunci</label><select name="kunci"><?php foreach (['A','B','C','D'] as $k): ?><option value="<?= $k ?>" <?= ($editSoal['kunci'] ?? 'A') === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
              </div>
            </div>
            <div class="soal-group" data-show="famili100">
              <div class="form-row"><label>Jawaban &amp; poin (satu baris: teks;poin)</label><textarea name="famili_jawaban" rows="6" placeholder="Dekomposisi;45&#10;Pengenalan Pola;25&#10;Algoritma;18&#10;Abstraksi;12"><?= e($editJawaban ?? '') ?></textarea></div>
              <p class="muted">Poin biasanya menurun dari jawaban terpopuler.</p>
            </div>
            <div class="soal-group" data-show="gesture">
              <div class="form-row"><label>Kategori (misal: Hewan, Aktivitas, Berpikir Komputasional)</label><input name="gest_kategori" value="<?= e($editKategori ?? '') ?>"></div>
              <p class="muted">Opsional — hanya label kecil di kartu soal Motion Quest AR.</p>
            </div>

            <div class="form-row"><label>Penjelasan / catatan (opsional)</label><input name="penjelasan" value="<?= e($editSoal['penjelasan'] ?? '') ?>"></div>
            <div class="inline">
              <?php if ($isAdmin): ?><label style="font-size:14px;font-weight:600"><input type="checkbox" name="is_demo" <?= ($editSoal['is_demo'] ?? 0) ? 'checked' : '' ?>> Soal demo (bisa dimainkan tanpa akun)</label><?php endif; ?>
              <button class="btn-primary"><?= $editSoal ? 'Simpan' : 'Tambah Soal' ?></button>
              <?php if ($editSoal): ?><a class="btn btn-secondary" href="?tab=soal">Batal</a><?php endif; ?>
            </div>
          </form>
        </div></div>

        <div class="dash-card"><div class="head">⬆ Impor Soal dari CSV <a class="btn btn-secondary btn-sm" href="?dl=template">⬇ Unduh Template</a></div><div class="body">
          <p class="muted">Kolom CSV: <code>soal;opsiA;opsiB;opsiC;opsiD;kunci;penjelasan</code> (kunci = A/B/C/D, boleh juga ditulis teks opsi yang benar). Format lama <code>soal;opsiA;opsiB;kunci;penjelasan</code> tetap dikenali untuk game A/B. Pemisah bisa <code>;</code> atau <code>,</code>.</p>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="soal_upload">
            <div class="inline">
              <div class="form-row"><label>Game</label><select name="game_id"><?php foreach ($games as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $soalFilter === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option><?php endforeach; ?></select></div>
              <div class="form-row"><label>Mata pelajaran</label><select name="mapel_id"><option value="0">— tanpa mapel —</option><?php foreach ($mapel as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['nama']) ?></option><?php endforeach; ?></select></div>
              <div class="form-row" style="max-width:160px"><label>Kelas (pemisah materi)</label><select name="kelas"><option value="">Semua kelas</option><?php foreach (['7', '8', '9'] as $k): ?><option value="<?= $k ?>">Kelas <?= $k ?></option><?php endforeach; ?></select></div>
              <div class="form-row"><label>File CSV</label><input type="file" name="csv" accept=".csv,.txt" required></div>
              <button class="btn-primary">⬆ Impor</button>
            </div>
          </form>
        </div></div>

        <div class="dash-card"><div class="head">Daftar Soal <span class="muted" style="font-weight:500">(<?= count($soalList) ?>)</span></div><div class="body">
          <form method="get" class="inline" style="margin-bottom:14px">
            <input type="hidden" name="tab" value="soal">
            <div class="form-row"><label>Game</label><select name="game" onchange="this.form.submit()"><?php foreach ($games as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $soalFilter === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="form-row"><label>Mapel</label><select name="mapel" onchange="this.form.submit()"><option value="0">Semua</option><?php foreach ($mapel as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $mapelFilter === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="form-row"><label>Kelas</label><select name="kelas" onchange="this.form.submit()"><option value="">Semua</option><option value="umum" <?= $kelasFilter === 'umum' ? 'selected' : '' ?>>Umum (semua kelas)</option><?php foreach (['7', '8', '9'] as $k): ?><option value="<?= $k ?>" <?= $kelasFilter === $k ? 'selected' : '' ?>>Kelas <?= $k ?></option><?php endforeach; ?></select></div>
          </form>
          <?php if (!$soalList): ?><p class="muted">Belum ada soal.</p><?php endif; ?>
          <table><tr><th>#</th><th>Pertanyaan</th><th>Opsi</th><th>Kunci</th><th>Mapel</th><th>Kelas</th><th>Aksi</th></tr>
          <?php foreach ($soalList as $i => $s): ?><tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($s['pertanyaan']) ?><div class="muted"><?= e($s['penjelasan']) ?></div></td>
            <td>A. <?= e($s['opsi_a']) ?><br>B. <?= e($s['opsi_b']) ?><?= !empty($s['opsi_c']) ? '<br>C. ' . e($s['opsi_c']) : '' ?><?= !empty($s['opsi_d']) ? '<br>D. ' . e($s['opsi_d']) : '' ?></td>
            <td><b><?= e($s['kunci']) ?></b></td>
            <td><?= $s['mapel_nama'] ? '<span class="badge">' . e($s['mapel_nama']) . '</span>' : '<span class="muted">—</span>' ?></td>
            <td><span class="badge"><?= $s['kelas'] ? 'Kelas ' . e($s['kelas']) : 'Semua' ?></span><?= (int) $s['is_demo'] ? ' <span class="badge">Demo</span>' : '' ?></td>
            <td><div class="inline" style="align-items:center">
              <a class="btn btn-secondary btn-sm" href="?tab=soal&edit=<?= (int) $s['id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Hapus soal ini?')" style="display:inline"><input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="soal_delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn-danger btn-sm">Hapus</button></form>
            </div></td>
          </tr><?php endforeach; ?></table>
        </div></div>
      <?php endif; ?>

      <?php if ($tab === 'users' && $isAdmin): ?>
        <div class="dash-card"><div class="head"><?= $editUser ? '✏️ Edit Pengguna' : '➕ Tambah Pengguna' ?></div><div class="body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="user_save"><input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
            <div class="inline">
              <div class="form-row"><label>Username</label><input name="username" required value="<?= e($editUser['username'] ?? '') ?>"></div>
              <div class="form-row"><label>Nama lengkap</label><input name="nama" required value="<?= e($editUser['nama'] ?? '') ?>"></div>
              <div class="form-row" style="max-width:150px"><label>Peran</label><select name="role"><option value="guru" <?= ($editUser['role'] ?? 'guru') === 'guru' ? 'selected' : '' ?>>Guru</option><option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></div>
              <div class="form-row"><label><?= $editUser ? 'Password baru (kosongkan jika tetap)' : 'Password' ?></label><input type="password" name="password" <?= $editUser ? '' : 'required' ?>></div>
            </div>
            <div class="inline"><button class="btn-primary"><?= $editUser ? 'Simpan' : 'Tambah' ?></button><?php if ($editUser): ?><a class="btn btn-secondary" href="?tab=users">Batal</a><?php endif; ?></div>
          </form>
        </div></div>
        <div class="dash-card"><div class="head">Daftar Pengguna</div><div class="body">
          <table><tr><th>#</th><th>Username</th><th>Nama</th><th>Peran</th><th>Aksi</th></tr>
          <?php foreach ($users as $i => $u): ?><tr>
            <td><?= $i + 1 ?></td><td><?= e($u['username']) ?></td><td><?= e($u['nama']) ?></td><td><span class="badge <?= $u['role'] === 'admin' ? 'admin' : '' ?>"><?= e($u['role']) ?></span></td>
            <td><div class="inline" style="align-items:center">
              <a class="btn btn-secondary btn-sm" href="?tab=users&edit=<?= (int) $u['id'] ?>">Edit</a>
              <?php if ((int) $u['id'] !== (int) $user['id']): ?><form method="post" onsubmit="return confirm('Hapus pengguna ini?')" style="display:inline"><input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>"><input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn-danger btn-sm">Hapus</button></form><?php endif; ?>
            </div></td>
          </tr><?php endforeach; ?></table>
        </div></div>
      <?php endif; ?>
    </main>
  </div>
</div>
<script>
(function () {
  function toggleSoalGroups() {
    var sel = document.getElementById('gameSelect'); if (!sel) return;
    var tipe = sel.options[sel.selectedIndex].getAttribute('data-tipe') || 'benar_salah';
    document.querySelectorAll('.soal-group').forEach(function (g) {
      var show = (g.getAttribute('data-show') || '').split(',').indexOf(tipe) !== -1;
      g.hidden = !show;
      g.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = !show; });
    });
  }
  document.addEventListener('DOMContentLoaded', toggleSoalGroups);
  var gs = document.getElementById('gameSelect'); if (gs) gs.addEventListener('change', toggleSoalGroups);
})();
</script>
</body>
</html>
