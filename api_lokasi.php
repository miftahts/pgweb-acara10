<?php
// Mengeluarkan data lokasi dalam format GeoJSON
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$rows = $pdo->query('SELECT id, nama, kategori, alamat, keterangan, latitude, longitude FROM lokasi')->fetchAll();

$features = [];
foreach ($rows as $r) {
    $features[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [(float)$r['longitude'], (float)$r['latitude']], // GeoJSON: lon, lat
        ],
        'properties' => [
            'id'         => (int)$r['id'],
            'nama'       => $r['nama'],
            'kategori'   => $r['kategori'],
            'alamat'     => $r['alamat'],
            'keterangan' => $r['keterangan'],
        ],
    ];
}
echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);