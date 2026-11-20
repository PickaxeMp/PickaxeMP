<?php
$json = json_decode(file_get_contents('.team/ref/required_item_list.json'), true);
$v = []; $cb = 0;
foreach ($json as $k => $e) { $v[$e['version'] ?? -1] = ($v[$e['version'] ?? -1] ?? 0) + 1; if (!empty($e['component_based'])) $cb++; }
echo "versions: " . json_encode($v) . "\ncomponent_based: $cb / " . count($json) . "\n";
$min = 32767; $max = -32768; $dup = [];
$seen = [];
foreach ($json as $k => $e) { $id = $e['runtime_id']; $min = min($min,$id); $max = max($max,$id); if (isset($seen[$id])) { $dup[$id] = [$seen[$id], $k]; } else { $seen[$id] = $k; } }
echo "id range: $min..$max\ndup ids: " . count($dup) . "\n";
echo json_encode(array_slice($dup, 0, 5)) . "\n";
