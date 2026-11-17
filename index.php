<?php
require 'db.php';
$rows = $pdo->query('SELECT id, nama, kategori, alamat, latitude, longitude FROM lokasi ORDER BY nama')->fetchAll();

// Warna marker per kategori (kategori lain otomatis abu-abu)
$warna = [
    'Pendidikan'   => '#2d6cdf',
    'Kesehatan'    => '#d93a3a',
    'Wisata'       => '#2e9a4f',
    'Transportasi' => '#e08a1e',
    'Perbelanjaan' => '#8a4fd1',
];
function warnaKat($k, $w) { return $w[$k] ?? '#6b7280'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Web GIS Latihan 10</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-measure@3.1.0/dist/leaflet-measure.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
<style>
  :root { --bg:#f6f7f4; --ink:#1d2b2a; --line:#d5d9d2; --accent:#1f6f6b; --hl:#fff2c2; }
  * { box-sizing: border-box; }
  body { margin:0; font-family: system-ui, "Segoe UI", sans-serif; color:var(--ink); background:var(--bg);
         display:grid; grid-template-columns: 380px 1fr; height:100vh; }
  aside { display:flex; flex-direction:column; border-right:1px solid var(--line); min-height:0; }
  aside header { padding:14px 16px; border-bottom:1px solid var(--line); }
  aside h1 { font-size:1.1rem; margin:0 0 8px; }
  .sukses { background:#e3f4e8; border:1px solid #2e9a4f; padding:6px 10px; border-radius:6px; margin-bottom:8px; font-size:.9rem; }
  .filter { display:flex; gap:8px; }
  #cari, #kat { padding:8px 10px; border:1px solid var(--line); border-radius:6px; font:inherit; background:#fff; }
  #cari { flex:1; min-width:0; }
  #cari:focus-visible, #kat:focus-visible, tr:focus-visible { outline:2px solid var(--accent); outline-offset:1px; }
  .tabel { overflow:auto; flex:1; }
  table { width:100%; border-collapse:collapse; font-size:.9rem; }
  th, td { text-align:left; padding:8px 12px; border-bottom:1px solid var(--line); }
  th { position:sticky; top:0; background:var(--bg); }
  tbody tr { cursor:pointer; }
  tbody tr:hover { background:#eef2ec; }
  tbody tr.aktif { background:var(--hl); }
  td small { color:#5b6a67; display:block; }
  td a { color:var(--accent); font-weight:600; }
  .dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
  #map { height:100%; width:100%; }
  .pin { width:22px; height:22px; border-radius:50%; border:3px solid #fff; box-shadow:0 1px 5px rgba(0,0,0,.55); }
  .leaflet-routing-container { max-height:40vh; overflow:auto; max-width:300px; font-size:.85rem; }
  .aksi button { margin:6px 6px 0 0; padding:4px 8px; font:inherit; cursor:pointer; }
  @media (max-width: 800px) {
    body { grid-template-columns:1fr; grid-template-rows: 45vh 1fr; }
    aside { order:2; border-right:0; border-top:1px solid var(--line); }
  }
</style>
</head>
<body>
<aside>
  <header>
    <h1>Data lokasi</h1>
    <?php if (($_GET['status'] ?? '') === 'diperbarui'): ?>
      <div class="sukses">Data lokasi berhasil diperbarui.</div>
    <?php endif; ?>
    <div class="filter">
      <input id="cari" type="search" placeholder="Cari nama atau alamat" aria-label="Cari lokasi">
      <select id="kat" aria-label="Filter kategori"><option value="">Semua</option></select>
    </div>
  </header>
  <div class="tabel">
    <table>
      <thead><tr><th>Nama</th><th>Kategori</th><th>Aksi</th></tr></thead>
      <tbody id="isi">
        <?php foreach ($rows as $r): ?>
        <tr tabindex="0" data-id="<?= (int)$r['id'] ?>" data-kat="<?= htmlspecialchars($r['kategori']) ?>">
          <td><?= htmlspecialchars($r['nama']) ?><small><?= htmlspecialchars($r['alamat'] ?? '') ?></small></td>
          <td><span class="dot" style="background:<?= warnaKat($r['kategori'], $warna) ?>"></span><?= htmlspecialchars($r['kategori']) ?></td>
          <td><a href="edit.php?id=<?= (int)$r['id'] ?>" onclick="event.stopPropagation()">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</aside>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-measure@3.1.0/dist/leaflet-measure.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
<script>
var WARNA = <?= json_encode($warna) ?>;
var map = L.map('map').setView([-7.677567, 110.406899], 12);

/* Basemap */
var esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
  { attribution: 'Tiles &copy; Esri' });
var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' });
osm.addTo(map);

/* Routing (OSRM) */
var asal = null, tujuan = null;
var rute = L.Routing.control({
  waypoints: [],
  addWaypoints: false,
  routeWhileDragging: false,
  show: true,
  lineOptions: { styles: [{ color: '#1f6f6b', weight: 5 }] },
  createMarker: function () { return null; }   // pakai marker milik kita
}).addTo(map);

function aturRute(latlng, jenis) {
  if (jenis === 'asal') asal = latlng; else tujuan = latlng;
  if (asal && tujuan) rute.setWaypoints([asal, tujuan]);
}
function hapusRute() { asal = tujuan = null; rute.setWaypoints([]); }

/* Marker, cluster, dan layer per kategori */
var markers = {};    // id -> marker
var info = {};       // id -> {kategori, teks}
var kelompok = {};   // kategori -> markerClusterGroup
var overlays = {};

function ikon(kat) {
  var c = WARNA[kat] || '#6b7280';
  return L.divIcon({ className: '', html: '<div class="pin" style="background:' + c + '"></div>',
                     iconSize: [22, 22], iconAnchor: [11, 11], popupAnchor: [0, -12] });
}

function buatPopup(p, ll) {
  var el = document.createElement('div');
  var b = document.createElement('strong'); b.textContent = p.nama;
  var k = document.createElement('div');    k.textContent = p.kategori + (p.alamat ? ' - ' + p.alamat : '');
  var t = document.createElement('div');    t.textContent = p.keterangan || '';
  var aksi = document.createElement('div'); aksi.className = 'aksi';
  [['Dari sini', function () { aturRute(L.latLng(ll), 'asal'); map.closePopup(); }],
   ['Ke sini',   function () { aturRute(L.latLng(ll), 'tujuan'); map.closePopup(); }],
   ['Hapus rute', function () { hapusRute(); map.closePopup(); }],
   ['Edit',      function () { window.location.href = 'edit.php?id=' + p.id; }]
  ].forEach(function (x) {
    var btn = document.createElement('button'); btn.textContent = x[0]; btn.onclick = x[1]; aksi.appendChild(btn);
  });
  el.append(b, k, t, aksi);
  return el;
}

function pilihBaris(id) {
  document.querySelectorAll('#isi tr').forEach(function (tr) { tr.classList.toggle('aktif', tr.dataset.id == id); });
  var baris = document.querySelector('#isi tr[data-id="' + id + '"]');
  if (baris) baris.scrollIntoView({ block: 'nearest' });
}

fetch('api_lokasi.php')
  .then(function (r) { return r.json(); })
  .then(function (geojson) {
    var kategoriAda = [];
    geojson.features.forEach(function (f) {
      var p = f.properties;
      var ll = [f.geometry.coordinates[1], f.geometry.coordinates[0]];
      var m = L.marker(ll, { icon: ikon(p.kategori) }).bindPopup(buatPopup(p, ll));
      m.on('click', function () { pilihBaris(p.id); });
      markers[p.id] = m;
      info[p.id] = p.kategori;
      if (!kelompok[p.kategori]) {
        kelompok[p.kategori] = L.markerClusterGroup({ maxClusterRadius: 45 }).addTo(map);
        overlays[p.kategori] = kelompok[p.kategori];
        kategoriAda.push(p.kategori);
      }
      kelompok[p.kategori].addLayer(m);
    });

    L.control.layers({ "OpenStreetMap": osm, "Esri Imagery": esri }, overlays, { collapsed: false }).addTo(map);

    var pilihan = document.getElementById('kat');
    kategoriAda.sort().forEach(function (k) {
      var o = document.createElement('option'); o.value = k; o.textContent = k; pilihan.appendChild(o);
    });

    var semua = Object.values(markers);
    if (semua.length) map.fitBounds(L.featureGroup(semua).getBounds().pad(0.2));
  })
  .catch(function () { alert('Data lokasi gagal dimuat dari api_lokasi.php'); });

/* Pengukuran dan scale bar */
L.control.measure({
  position: 'topleft',
  primaryLengthUnit: 'kilometers', secondaryLengthUnit: 'meters',
  primaryAreaUnit: 'hectares', secondaryAreaUnit: 'sqmeters',
  localization: 'en'
}).addTo(map);
L.control.scale({ maxWidth: 150, position: 'bottomright', imperial: false }).addTo(map);

/* Klik baris tabel -> zoom ke titik (membuka cluster bila perlu) */
function fokus(tr) {
  var m = markers[tr.dataset.id];
  var g = kelompok[info[tr.dataset.id]];
  if (!m || !g) return;
  pilihBaris(tr.dataset.id);
  if (g.hasLayer(m)) {
    g.zoomToShowLayer(m, function () { m.openPopup(); });
  } else {
    map.flyTo(m.getLatLng(), 16);
  }
}
var isi = document.getElementById('isi');
isi.addEventListener('click', function (e) { var tr = e.target.closest('tr'); if (tr) fokus(tr); });
isi.addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && e.target.tagName !== 'A') { var tr = e.target.closest('tr'); if (tr) fokus(tr); }
});

/* Pencarian + filter kategori: tabel dan marker ikut disaring */
function saring() {
  var q = document.getElementById('cari').value.toLowerCase();
  var kat = document.getElementById('kat').value;
  document.querySelectorAll('#isi tr').forEach(function (tr) {
    // hanya kolom Nama dan Kategori yang dicari (kolom Aksi diabaikan)
    var teks = (tr.cells[0].textContent + ' ' + tr.cells[1].textContent).toLowerCase();
    var cocok = teks.indexOf(q) !== -1 && (!kat || tr.dataset.kat === kat);
    tr.style.display = cocok ? '' : 'none';
    var m = markers[tr.dataset.id], g = kelompok[info[tr.dataset.id]];
    if (!m || !g) return;
    if (cocok && !g.hasLayer(m)) g.addLayer(m);
    if (!cocok && g.hasLayer(m)) g.removeLayer(m);
  });
}
document.getElementById('cari').addEventListener('input', saring);
document.getElementById('kat').addEventListener('change', saring);
</script>
</body>
</html>