<?php
require 'db.php';

$daftarKategori = ['Pendidikan', 'Kesehatan', 'Wisata', 'Transportasi', 'Perbelanjaan'];

// 1. Ambil id dari URL (GET) atau form (POST), lalu cari datanya
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM lokasi WHERE id = ?');
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    http_response_code(404);
    exit('Data lokasi tidak ditemukan.');
}

// 2. Proses form saat disimpan
$error = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = trim($_POST['nama'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $alamat     = trim($_POST['alamat'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $lat        = $_POST['latitude'] ?? '';
    $lng        = $_POST['longitude'] ?? '';

    // Validasi
    if ($nama === '')     $error[] = 'Nama wajib diisi.';
    if ($kategori === '') $error[] = 'Kategori wajib dipilih.';
    if (!is_numeric($lat) || $lat < -90 || $lat > 90)    $error[] = 'Latitude harus angka antara -90 dan 90.';
    if (!is_numeric($lng) || $lng < -180 || $lng > 180)  $error[] = 'Longitude harus angka antara -180 dan 180.';

    if (!$error) {
        $sql = 'UPDATE lokasi
                SET nama = ?, kategori = ?, alamat = ?, keterangan = ?, latitude = ?, longitude = ?
                WHERE id = ?';
        $pdo->prepare($sql)->execute([$nama, $kategori, $alamat, $keterangan, (float)$lat, (float)$lng, $id]);
        header('Location: index.php?status=diperbarui');
        exit;
    }

    // Jika ada error, tampilkan kembali isian terakhir pengguna
    $data = array_merge($data, [
        'nama' => $nama, 'kategori' => $kategori, 'alamat' => $alamat,
        'keterangan' => $keterangan, 'latitude' => $lat, 'longitude' => $lng,
    ]);
}

// Pastikan kategori lama yang tidak ada di daftar tetap muncul
if (!in_array($data['kategori'], $daftarKategori, true) && $data['kategori'] !== '') {
    $daftarKategori[] = $data['kategori'];
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Lokasi</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  :root { --bg:#f6f7f4; --ink:#1d2b2a; --line:#d5d9d2; --accent:#1f6f6b; }
  * { box-sizing: border-box; }
  body { margin:0; font-family: system-ui, "Segoe UI", sans-serif; color:var(--ink); background:var(--bg); }
  .wrap { max-width:720px; margin:24px auto; padding:0 16px; }
  h1 { font-size:1.3rem; }
  label { display:block; margin:12px 0 4px; font-weight:600; }
  input, select, textarea { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:6px; font:inherit; background:#fff; }
  input:focus-visible, select:focus-visible, textarea:focus-visible { outline:2px solid var(--accent); outline-offset:1px; }
  .baris { display:flex; gap:12px; }
  .baris > div { flex:1; }
  #peta { height:280px; border:1px solid var(--line); border-radius:6px; margin-top:6px; }
  .error { background:#fde8e8; border:1px solid #d93a3a; padding:8px 12px; border-radius:6px; margin-bottom:12px; }
  .aksi { margin-top:16px; display:flex; gap:10px; }
  button, .batal { padding:9px 16px; border-radius:6px; font:inherit; cursor:pointer; text-decoration:none; }
  button { background:var(--accent); color:#fff; border:0; }
  .batal { border:1px solid var(--line); color:var(--ink); background:#fff; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Edit lokasi #<?= (int)$data['id'] ?></h1>

  <?php if ($error): ?>
    <div class="error"><?php foreach ($error as $m) echo '<div>' . e($m) . '</div>'; ?></div>
  <?php endif; ?>

  <form method="post" action="edit.php?id=<?= (int)$data['id'] ?>">
    <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">

    <label for="nama">Nama</label>
    <input id="nama" name="nama" value="<?= e($data['nama']) ?>" required>

    <label for="kategori">Kategori</label>
    <select id="kategori" name="kategori" required>
      <?php foreach ($daftarKategori as $k): ?>
        <option value="<?= e($k) ?>" <?= $k === $data['kategori'] ? 'selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="alamat">Alamat</label>
    <input id="alamat" name="alamat" value="<?= e($data['alamat']) ?>">

    <label for="keterangan">Keterangan</label>
    <textarea id="keterangan" name="keterangan" rows="3"><?= e($data['keterangan']) ?></textarea>

    <div class="baris">
      <div>
        <label for="latitude">Latitude</label>
        <input id="latitude" name="latitude" value="<?= e($data['latitude']) ?>" required>
      </div>
      <div>
        <label for="longitude">Longitude</label>
        <input id="longitude" name="longitude" value="<?= e($data['longitude']) ?>" required>
      </div>
    </div>

    <label>Geser marker atau klik peta untuk mengubah koordinat</label>
    <div id="peta"></div>

    <div class="aksi">
      <button type="submit">Simpan perubahan</button>
      <a class="batal" href="index.php">Batal</a>
    </div>
  </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var inLat = document.getElementById('latitude');
var inLng = document.getElementById('longitude');
var awal = [parseFloat(inLat.value) || -7.7956, parseFloat(inLng.value) || 110.3695];

var peta = L.map('peta').setView(awal, 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(peta);

var marker = L.marker(awal, { draggable: true }).addTo(peta);

function isiKoordinat(ll) {
  inLat.value = ll.lat.toFixed(6);
  inLng.value = ll.lng.toFixed(6);
}
marker.on('dragend', function () { isiKoordinat(marker.getLatLng()); });
peta.on('click', function (e) { marker.setLatLng(e.latlng); isiKoordinat(e.latlng); });

// Jika koordinat diketik manual, marker ikut berpindah
function geser() {
  var la = parseFloat(inLat.value), ln = parseFloat(inLng.value);
  if (!isNaN(la) && !isNaN(ln)) { marker.setLatLng([la, ln]); peta.panTo([la, ln]); }
}
inLat.addEventListener('change', geser);
inLng.addEventListener('change', geser);
</script>
</body>
</html>