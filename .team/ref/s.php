<?php
$p = 'docs/protocol/schemas/v1.26.65/StartGamePacketPayload.json';
$j = json_decode(file_get_contents($p), true);
foreach ($j['properties'] ?? [] as $k => $v) { echo $k, "\n"; }
echo "--- required ---\n";
var_export($j['required'] ?? []);
