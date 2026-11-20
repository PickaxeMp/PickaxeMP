<?php
declare(strict_types=1);
$src = '.team/ref/required_item_list.json';
$data = json_decode(file_get_contents($src), true);
if (!is_array($data)) { fwrite(STDERR, "kaynak okunamadi\n"); exit(1); }
$items = [];
foreach ($data as $name => $entry) {
    $items[] = [
        (string) $name,
        (int) ($entry['runtime_id'] ?? 0),
        (bool) ($entry['component_based'] ?? false),
        (int) ($entry['version'] ?? 0),
    ];
}
usort($items, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
$payload = [
    'source' => 'pmmp/BedrockData required_item_list.json (master, indirildi 2026-11-20)',
    'protocol' => 2225,
    'items' => $items,
];
file_put_contents('resources/item-registry.json', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
echo 'yazildi: ' . count($items) . " giris, " . filesize('resources/item-registry.json') . " bayt\n";
