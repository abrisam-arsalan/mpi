<?php
// File ini ada di subfolder api/, sedangkan config.php ada satu tingkat di atas
// (portal/config.php). Memakai __DIR__ . '/config.php' akan menunjuk ke
// portal/api/config.php yang tidak ada → fatal error → HTTP 500 pada SEMUA
// permintaan bank soal. Karena itu path-nya harus naik satu tingkat.
require dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

// ------------------------------------------------------------
// Sumber & filter soal.
//
// Game (1 file HTML mandiri) hanya mengirim ?game=slug. Pilihan guru di
// halaman setup (play.php) disimpan play.php ke $_SESSION['mpi_setup']
// per game — file game TIDAK perlu diubah. Parameter GET eksplisit
// (?sumber=demo|guru, ?kelas=7|8|9, ?mapel=id) menang bila ada.
//
// - sumber=demo → hanya soal demo (is_demo = 1), bisa dimainkan tanpa akun
// - sumber=guru → hanya soal bank soal (is_demo = 0)
// - sumber kosong → semua soal (kompatibel dengan perilaku lama)
// - kelas 7/8/9 → soal kelas itu + soal berlaku semua kelas (kelas NULL)
// ------------------------------------------------------------
try {
    $slug = $_GET['game'] ?? '';
    if ($slug === '') {
        echo json_encode(['error' => 'Parameter ?game=slug diperlukan'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = mpi_db()->prepare('SELECT id, slug, nama, tipe FROM mpi_games WHERE slug = ? AND aktif = 1');
    $st->execute([$slug]);
    $game = $st->fetch();
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game tidak ditemukan'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $setup = $_SESSION['mpi_setup'][(int) $game['id']] ?? [];

    // Urutan soal: default acak; ?order=asc untuk urutan level (mis. Millionaire)
    $order = (($_GET['order'] ?? '') === 'asc') ? 's.id ASC' : 'RAND()';
    $mapel = (int) ($_GET['mapel'] ?? ($setup['mapel'] ?? 0));
    $kelas = (string) ($_GET['kelas'] ?? ($setup['kelas'] ?? ''));
    if (!in_array($kelas, ['7', '8', '9'], true)) { $kelas = ''; }
    $sumber = (string) ($_GET['sumber'] ?? ($setup['sumber'] ?? ''));
    if (!in_array($sumber, ['demo', 'guru'], true)) { $sumber = ''; }

    $ambil = function (string $sumberQ, string $kelasQ, int $mapelQ) use ($game, $order): array {
        $sql = 'SELECT s.id, s.pertanyaan, s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, s.kunci, s.penjelasan, s.payload, s.is_demo, m.nama AS mapel_nama
                FROM mpi_soal s LEFT JOIN mpi_mapel m ON m.id = s.mapel_id
                WHERE s.game_id = ?';
        $params = [$game['id']];
        if ($sumberQ === 'demo') { $sql .= ' AND s.is_demo = 1'; }
        elseif ($sumberQ === 'guru') { $sql .= ' AND s.is_demo = 0'; }
        if ($kelasQ !== '') { $sql .= ' AND (s.kelas IS NULL OR s.kelas = ?)'; $params[] = $kelasQ; }
        if ($mapelQ > 0) { $sql .= ' AND s.mapel_id = ?'; $params[] = $mapelQ; }
        $sql .= ' ORDER BY ' . $order;
        $st = mpi_db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    };

    $soal = $ambil($sumber, $kelas, $mapel);

    // Fallback: soal guru tidak tersedia (belum dibuat / filter terlalu sempit)
    // → gunakan soal demo supaya permainan tetap jalan di depan kelas.
    $fallback = false;
    if (!$soal && $sumber === 'guru') {
        $soal = $ambil('demo', $kelas, $mapel);
        $fallback = (bool) $soal;
    }

    // Decode kolom JSON `payload` agar jadi objek/array, bukan string.
    foreach ($soal as &$s) {
        if (!empty($s['payload'])) {
            $decoded = json_decode($s['payload'], true);
            if (is_array($decoded)) { $s['payload'] = $decoded; }
        }
    }
    unset($s);

    // Ringkasan mata pelajaran (nama unik dari soal)
    $mapelNama = [];
    foreach ($soal as $s) {
        if (!empty($s['mapel_nama']) && !in_array($s['mapel_nama'], $mapelNama, true)) {
            $mapelNama[] = $s['mapel_nama'];
        }
    }

    echo json_encode([
        'game' => $game,
        'mapel' => $mapelNama,
        'soal' => $soal,
        'sumber' => $sumber,
        'fallback_demo' => $fallback,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
}
