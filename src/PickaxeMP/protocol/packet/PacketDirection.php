<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet;

/**
 * Paketin akış yönü. Registry bunu doğrulama için kullanır: sunucuya
 * istemciden gelmemesi gereken bir paket gelirse bu bir protokol ihlalidir.
 */
enum PacketDirection: string
{
    case ClientToServer = 'c2s';
    case ServerToClient = 's2c';
    case Both = 'both';

    public function allows(PacketDirection $actual): bool
    {
        return $this === self::Both || $this === $actual;
    }
}
