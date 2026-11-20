<?php
require 'vendor/autoload.php';
use PickaxeMP\protocol\packet\packets\ActorEventPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
$e = new ProtocolEngine(PacketRegistry::withSupportedPackets());
foreach ([new ActorEventPacket(5, ActorEventType::Death), new LevelSoundEventPacket('death', new Vec3(1.0,2.0,3.0), actorIdentifier: 'minecraft:player', isGlobal: true, actorUniqueId: 9)] as $p) {
  try {
    $bytes = $e->encode($p);
    echo get_class($p), ' -> ', strlen($bytes), ' bayt; hex=', bin2hex($bytes), "\n";
    $d = $e->decode($bytes, new PacketContext('c', PacketDirection::ServerToClient));
    echo '  decode: ', get_class($d), "\n";
  } catch (\Throwable $t) {
    echo get_class($p), ' HATA: ', get_class($t), ': ', $t->getMessage(), "\n";
  }
}
