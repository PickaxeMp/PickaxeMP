<?php
foreach (['ItemRegistryPacket.json','ItemRegistryPacketPayload.json','DataItemEntry.json','DataItemType.json'] as $f) {
  $p = 'docs/protocol/schemas/v1.26.65/' . $f;
  echo "=== $f\n";
  echo file_get_contents($p);
  echo "\n";
}
