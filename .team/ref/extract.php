<?php
$json = json_decode(file_get_contents('.team/ref/required_item_list.json'), true);
if (!is_array($json)) { fwrite(STDERR, "bad json\n"); exit(1); }
$ids = [
'minecraft:apple','minecraft:baked_potato','minecraft:beef','minecraft:beetroot','minecraft:beetroot_soup',
'minecraft:bread','minecraft:cake','minecraft:carrot','minecraft:chicken','minecraft:chorus_fruit',
'minecraft:cod','minecraft:cooked_beef','minecraft:cooked_chicken','minecraft:cooked_cod','minecraft:cooked_mutton',
'minecraft:cooked_porkchop','minecraft:cooked_rabbit','minecraft:cooked_salmon','minecraft:cookie','minecraft:dried_kelp',
'minecraft:enchanted_golden_apple','minecraft:golden_apple','minecraft:golden_carrot','minecraft:honey_bottle','minecraft:melon_slice',
'minecraft:mushroom_stew','minecraft:mutton','minecraft:porkchop','minecraft:potato','minecraft:pufferfish',
'minecraft:rabbit','minecraft:rabbit_stew','minecraft:rotten_flesh','minecraft:salmon','minecraft:spider_eye',
'minecraft:sweet_berries','minecraft:tropical_fish',
'minecraft:air','minecraft:stone','minecraft:dirt','minecraft:grass_block','minecraft:cobblestone','minecraft:oak_planks',
'minecraft:oak_log','minecraft:sand','minecraft:sandstone','minecraft:gravel','minecraft:glass','minecraft:coal',
'minecraft:diamond','minecraft:iron_ingot','minecraft:gold_ingot','minecraft:stick','minecraft:torch','minecraft:diamond_pickaxe',
'minecraft:diamond_axe','minecraft:diamond_shovel','minecraft:diamond_sword','minecraft:iron_pickaxe','minecraft:wooden_pickaxe',
'minecraft:bowl','minecraft:bucket','minecraft:water_bucket','minecraft:lava_bucket','minecraft:coal_block','minecraft:iron_block',
'minecraft:gold_block','minecraft:diamond_block','minecraft:crafting_table','minecraft:furnace','minecraft:chest',
];
$out = [];
$missing = [];
foreach ($ids as $id) {
    if (isset($json[$id])) { $out[$id] = $json[$id]; } else { $missing[] = $id; }
}
foreach ($out as $k => $v) { printf("%-40s net=%s legacy=%s cb=%s\n", $k, $v['runtime_id'] ?? '?', $v['legacy_id'] ?? '?', var_export($v['component_based'] ?? null, true)); }
if ($missing) { fwrite(STDERR, "MISSING: " . implode(', ', $missing) . "\n"); }
echo "count=" . count($out) . " total=" . count($json) . "\n";
echo "keys sample: " . json_encode(array_slice($json, 0, 2)) . "\n";
