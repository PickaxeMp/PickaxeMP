<?php
$j = json_decode(file_get_contents('resources/item-registry.json'), true);
$cb = [];
$ver = [];
foreach ($j['items'] as $r) { if ($r[2]) { $cb[] = $r[0].'#'.$r[1]; } $ver[$r[3]] = ($ver[$r[3]] ?? 0) + 1; }
echo "componentBased: " . count($cb) . "\n";
echo implode(', ', array_slice($cb, 0, 40)) . "\n";
echo "versions: " . json_encode($ver) . "\n";
