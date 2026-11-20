<?php
$j = json_decode(file_get_contents('resources/item-registry.json'), true);
$want = ['minecraft:apple','minecraft:porkchop','minecraft:rabbit_stew','minecraft:honey_bottle'];
foreach ($j['items'] as $r) { if (in_array($r[0], $want, true)) { echo json_encode($r), "\n"; } }
